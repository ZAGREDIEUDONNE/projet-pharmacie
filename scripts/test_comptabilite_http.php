<?php
declare(strict_types=1);
/** Non-destructive accounting HTTP test. Valid CSRF POSTs use read-only actions. */
$baseUrl = rtrim(getenv('COMPTABILITE_BASE_URL') ?: 'http://localhost', '/');
$unauthOnly = in_array('--unauth-only', $argv, true);
$rbacOnly = in_array('--rbac-only', $argv, true);
$cookieFile = tempnam(sys_get_temp_dir(), 'comptabilite_http_');
if ($cookieFile === false) throw new RuntimeException('Impossible de créer le fichier cookie temporaire.');

/** @return array{status:int,headers:array<string,string>,body:string,url:string} */
function comptaRequest(string $base, string $path, string $cookie, string $method = 'GET', array $data = [], array $headers = [], bool $json = false): array {
    $curl = curl_init($base . (str_starts_with($path, '/') ? $path : '/' . $path));
    if ($curl === false) throw new RuntimeException('Impossible d’initialiser cURL.');
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_TIMEOUT=>15,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers]);
    if ($method === 'POST') curl_setopt($curl, CURLOPT_POSTFIELDS, $json ? json_encode($data, JSON_THROW_ON_ERROR) : http_build_query($data));
    $raw = curl_exec($curl);
    if ($raw === false) { $error = curl_error($curl); curl_close($curl); throw new RuntimeException("Erreur HTTP cURL : {$error}"); }
    $length = (int)curl_getinfo($curl, CURLINFO_HEADER_SIZE); $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE); $url = (string)curl_getinfo($curl, CURLINFO_EFFECTIVE_URL); curl_close($curl);
    $responseHeaders=[]; foreach (preg_split('/\r?\n/', trim(substr($raw, 0, $length))) ?: [] as $line) if (str_contains($line, ':')) { [$key,$value]=explode(':',$line,2); $responseHeaders[strtolower(trim($key))]=trim($value); }
    return ['status'=>$status,'headers'=>$responseHeaders,'body'=>substr($raw,$length),'url'=>$url];
}
/** @return array{status:int,headers:array<string,string>,body:string,url:string,redirects:array<int,string>} */
function comptaFollowing(string $base, string $path, string $cookie, string $method = 'GET', array $data = [], array $headers = [], bool $json = false): array {
    $redirects=[]; $result=comptaRequest($base,$path,$cookie,$method,$data,$headers,$json);
    for($i=0;$i<5 && $result['status']>=300 && $result['status']<400;$i++) { $location=$result['headers']['location']??''; if($location==='')break; $redirects[]=$location; $result=comptaRequest($base,$location,$cookie); }
    $result['redirects']=$redirects; return $result;
}
function comptaCsrf(string $body): string { preg_match('/name=["\']csrf_token["\'][^>]*value=["\']([^"\']+)["\']/', $body, $match); return html_entity_decode($match[1]??'', ENT_QUOTES, 'UTF-8'); }
function comptaRoute(string $route, array $response, string $expectedContent = '', string $expectedContentType = ''): array { $body=$response['body']; $error=$response['status']>=500||stripos($body,'Erreur SQL')!==false||stripos($body,'Fatal error')!==false||stripos($body,'Route non trouvée')!==false; $found=$expectedContent===''||stripos($body,$expectedContent)!==false; $type=$response['headers']['content-type']??''; $typeOk=$expectedContentType===''||stripos($type,$expectedContentType)!==false; return ['route'=>$route,'status'=>$response['status'],'expected_content_found'=>$found,'content_type'=>$type,'server_error'=>$error,'pass'=>$response['status']===200&&!$error&&$found&&$typeOk]; }

try {
    $results=['base_url'=>$baseUrl,'unauthenticated'=>[],'authenticated'=>'NOT_RUN','routes'=>[],'api'=>[],'csrf'=>[],'rbac'=>[],'production'=>[],'limits'=>[],'non_regression'=>[]];
    
    // Production counters before tests
    $productionBefore=['ecritures'=>0,'lignes'=>0,'reglements'=>0,'ventes'=>0,'caisse'=>0,'ecriture_109'=>''];
    try {
        $pdo=new PDO('mysql:host=localhost;dbname=medecin;charset=utf8mb4','root','');
        $productionBefore['ecritures']=$pdo->query('SELECT COUNT(*) FROM ecritures_comptables')->fetchColumn();
        $productionBefore['lignes']=$pdo->query('SELECT COUNT(*) FROM lignes_ecritures')->fetchColumn();
        $productionBefore['reglements']=$pdo->query('SELECT COUNT(*) FROM client_reglements')->fetchColumn();
        $productionBefore['ventes']=$pdo->query('SELECT COUNT(*) FROM ventes')->fetchColumn();
        // Check if caisse_sessions table exists, use it instead of caisse_mouvements
        $caisseTableExists=$pdo->query("SHOW TABLES LIKE 'caisse_sessions'")->fetch();
        if($caisseTableExists){
            $productionBefore['caisse']=$pdo->query('SELECT COUNT(*) FROM caisse_sessions')->fetchColumn();
        }else{
            $productionBefore['caisse']='TABLE_NOT_FOUND';
        }
        $ecriture109=$pdo->query('SELECT * FROM ecritures_comptables WHERE id=109')->fetch(PDO::FETCH_ASSOC);
        $productionBefore['ecriture_109']=$ecriture109 ? json_encode($ecriture109) : 'NOT_FOUND';
    } catch (Exception $e) {
        $results['production']['error']='Impossible de connecter à medecin pour compteurs: '.$e->getMessage();
    }
    $results['production']['before']=$productionBefore;
    
    $html=comptaRequest($baseUrl,'/comptabilite',$cookieFile); $api=comptaRequest($baseUrl,'/comptabilite/api',$cookieFile,'GET',[],['X-Requested-With: XMLHttpRequest']); $apiPayload=json_decode($api['body'],true);
    $results['unauthenticated']=['dashboard'=>['status'=>$html['status'],'location'=>$html['headers']['location']??null,'pass'=>$html['status']>=300&&$html['status']<400],'api'=>['status'=>$api['status'],'content_type'=>$api['headers']['content-type']??'','json'=>is_array($apiPayload),'pass'=>$api['status']===401&&is_array($apiPayload)]];
    
    if($unauthOnly){$ok=!in_array(false,array_column($results['unauthenticated'],'pass'),true);$results['summary']=['unauthenticated_pass'=>$ok];echo json_encode($results,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;exit($ok?0:1);}
    
    // RBAC-only mode: test all roles without full authenticated tests
    if($rbacOnly){
        $roles=['COMPTABLE','VENDEUR','ASSISTANT','CHARGE_COMMANDE','ADMINISTRATEUR'];
        $roleCredentials=[];
        foreach($roles as $role){
            $roleCredentials[$role]=['login'=>getenv($role.'_LOGIN')?:'','password'=>getenv($role.'_PASSWORD')?:''];
        }
        foreach($roles as $role){
            $login=$roleCredentials[$role]['login'];
            $password=$roleCredentials[$role]['password'];
            if($login===''||$password===''){
                $results['rbac'][$role]='BLOCKED_MISSING_CREDENTIALS';
                continue;
            }
            $roleCookie=tempnam(sys_get_temp_dir(),'rbac_'.$role.'_');
            try {
                $loginPage=comptaRequest($baseUrl,'/login',$roleCookie);
                $loginResponse=comptaFollowing($baseUrl,'/login/auth',$roleCookie,'POST',['login'=>$login,'password'=>$password,'csrf_token'=>comptaCsrf($loginPage['body'])],['Content-Type: application/x-www-form-urlencoded']);
                if($loginResponse['status']!==200||stripos($loginResponse['body'],'Identifiants invalides')!==false){
                    $results['rbac'][$role]='AUTH_FAILED';
                    continue;
                }
                $dashboard=comptaFollowing($baseUrl,'/comptabilite',$roleCookie);
                $results['rbac'][$role]=['status'=>$dashboard['status'],'expected'=>$role==='COMPTABLE'?200:403,'pass'=>($role==='COMPTABLE'?$dashboard['status']===200:$dashboard['status']===403)];
            } finally { if(is_file($roleCookie))unlink($roleCookie); }
        }
        $rbacPass=!in_array(false,array_filter(array_column($results['rbac'],'pass'),fn($v)=>is_bool($v)),true);
        $results['summary']=['unauthenticated_pass'=>!in_array(false,array_column($results['unauthenticated'],'pass'),true),'rbac_pass'=>$rbacPass];
        echo json_encode($results,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;exit($results['summary']['unauthenticated_pass']&&$rbacPass?0:1);
    }
    
    $login=getenv('COMPTABLE_LOGIN')?:''; $password=getenv('COMPTABLE_PASSWORD')?:'';
    if($login===''||$password===''){ $results['authenticated']='BLOCKED_MISSING_COMPTABLE_LOGIN_OR_PASSWORD'; $results['limits'][]='Recette authentifiée et RBAC HTTP non exécutés : COMPTABLE_LOGIN et/ou COMPTABLE_PASSWORD absent(s).'; $results['summary']=['unauthenticated_pass'=>!in_array(false,array_column($results['unauthenticated'],'pass'),true),'authenticated'=>'BLOCKED']; echo json_encode($results,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL; exit($results['summary']['unauthenticated_pass']?0:1); }
    $loginPage=comptaRequest($baseUrl,'/login',$cookieFile); if($loginPage['status']!==200) throw new RuntimeException('GET /login retourne HTTP '.$loginPage['status']);
    $loginResponse=comptaFollowing($baseUrl,'/login/auth',$cookieFile,'POST',['login'=>$login,'password'=>$password,'csrf_token'=>comptaCsrf($loginPage['body'])],['Content-Type: application/x-www-form-urlencoded']);
    if($loginResponse['status']!==200||stripos($loginResponse['body'],'Identifiants invalides')!==false) throw new RuntimeException('Authentification COMPTABLE refusée.'); $results['authenticated']='PASS';
    $routes=['/comptabilite'=>['Dashboard Comptable',''], '/comptabilite/plan-comptable'=>['Plan comptable',''], '/comptabilite/journaux'=>['Journaux comptables',''], '/comptabilite/journaux/ventes'=>['Journal des Ventes',''], '/comptabilite/journaux/achats'=>['Journal des Achats',''], '/comptabilite/journaux/caisse'=>['Journal de Caisse',''], '/comptabilite/grand-livre'=>['Grand livre',''], '/comptabilite/balance'=>['Balance generale',''], '/comptabilite/etats-financiers'=>['Etats financiers',''], '/comptabilite/suivi-tiers'=>['Suivi tiers',''], '/comptabilite/tva'=>['TVA',''], '/comptabilite/integration'=>['Integration comptable',''], '/comptabilite/exporter'=>['','text/csv']];
    foreach($routes as $route=>[$content,$contentType]) $results['routes'][]=comptaRoute($route,comptaFollowing($baseUrl,$route,$cookieFile),$content,$contentType);
    $apiRead=comptaFollowing($baseUrl,'/comptabilite/api',$cookieFile,'GET',[],['X-Requested-With: XMLHttpRequest']); $results['api']['read']=['status'=>$apiRead['status'],'json'=>is_array(json_decode($apiRead['body'],true)),'pass'=>$apiRead['status']===200&&is_array(json_decode($apiRead['body'],true))];
    foreach(['initialiser_plan_comptable','creer_journaux','initialiser_tva','integrer_ventes','integrer_commandes','integrer_caisse'] as $action){$response=comptaFollowing($baseUrl,'/comptabilite/api?action='.rawurlencode($action),$cookieFile,'GET',[],['X-Requested-With: XMLHttpRequest']);$results['api']['mutative_get'][]=['action'=>$action,'status'=>$response['status'],'pass'=>$response['status']===405];}
    $without=comptaFollowing($baseUrl,'/comptabilite/api',$cookieFile,'POST',['action'=>'verifier_equilibre'],['Content-Type: application/json','X-Requested-With: XMLHttpRequest'],true);$results['csrf']['api_without_token']=['status'=>$without['status'],'pass'=>$without['status']===403];
    // Use GET for verifier_equilibre as it's a read-only action that doesn't require CSRF
    $withGet=comptaFollowing($baseUrl,'/comptabilite/api?action=verifier_equilibre',$cookieFile,'GET',[],['X-Requested-With: XMLHttpRequest']);$results['csrf']['api_get_readonly']=['status'=>$withGet['status'],'json'=>is_array(json_decode($withGet['body'],true)),'pass'=>$withGet['status']===200&&is_array(json_decode($withGet['body'],true))];
    $page=comptaFollowing($baseUrl,'/comptabilite',$cookieFile); $token=comptaCsrf($page['body']);
    $with=comptaFollowing($baseUrl,'/comptabilite/api',$cookieFile,'POST',['action'=>'verifier_equilibre','csrf_token'=>$token],['Content-Type: application/json','X-Requested-With: XMLHttpRequest'],true);$results['csrf']['api_json_with_token']=['status'=>$with['status'],'json'=>is_array(json_decode($with['body'],true)),'pass'=>$with['status']===200&&is_array(json_decode($with['body'],true))];
    $noIntegration=comptaFollowing($baseUrl,'/comptabilite/integration',$cookieFile,'POST',['action'=>'etat'],['Content-Type: application/x-www-form-urlencoded']);$results['csrf']['integration_without_token']=['status'=>$noIntegration['status'],'pass'=>$noIntegration['status']===403];
    $yesIntegration=comptaFollowing($baseUrl,'/comptabilite/integration',$cookieFile,'POST',['action'=>'etat','csrf_token'=>$token],['Content-Type: application/x-www-form-urlencoded']);$results['csrf']['integration_with_token']=['status'=>$yesIntegration['status'],'json'=>is_array(json_decode($yesIntegration['body'],true)),'pass'=>$yesIntegration['status']===200&&is_array(json_decode($yesIntegration['body'],true))];
    
    // RBAC tests for all roles
    $roles=['VENDEUR','ASSISTANT','CHARGE_COMMANDE','ADMINISTRATEUR'];
    $roleCredentials=[];
    foreach($roles as $role){
        $roleCredentials[$role]=['login'=>getenv($role.'_LOGIN')?:'','password'=>getenv($role.'_PASSWORD')?:''];
    }
    foreach($roles as $role){
        $login=$roleCredentials[$role]['login'];
        $password=$roleCredentials[$role]['password'];
        if($login===''||$password===''){
            $results['rbac'][$role]='BLOCKED_MISSING_CREDENTIALS';
            $results['limits'][]="RBAC $role: identifiants absents";
            continue;
        }
        $roleCookie=tempnam(sys_get_temp_dir(),'rbac_'.$role.'_');
        try {
            $loginPage=comptaRequest($baseUrl,'/login',$roleCookie);
            $loginResponse=comptaFollowing($baseUrl,'/login/auth',$roleCookie,'POST',['login'=>$login,'password'=>$password,'csrf_token'=>comptaCsrf($loginPage['body'])],['Content-Type: application/x-www-form-urlencoded']);
            if($loginResponse['status']!==200||stripos($loginResponse['body'],'Identifiants invalides')!==false){
                $results['rbac'][$role]='AUTH_FAILED';
                continue;
            }
            $dashboard=comptaFollowing($baseUrl,'/comptabilite',$roleCookie);
            $results['rbac'][$role]=['status'=>$dashboard['status'],'expected'=>403,'pass'=>$dashboard['status']===403];
        } finally { if(is_file($roleCookie))unlink($roleCookie); }
    }
    
    // Production counters after tests
    $productionAfter=['ecritures'=>0,'lignes'=>0,'reglements'=>0,'ventes'=>0,'caisse'=>0,'ecriture_109'=>''];
    try {
        $pdo=new PDO('mysql:host=localhost;dbname=medecin;charset=utf8mb4','root','');
        $productionAfter['ecritures']=$pdo->query('SELECT COUNT(*) FROM ecritures_comptables')->fetchColumn();
        $productionAfter['lignes']=$pdo->query('SELECT COUNT(*) FROM lignes_ecritures')->fetchColumn();
        $productionAfter['reglements']=$pdo->query('SELECT COUNT(*) FROM client_reglements')->fetchColumn();
        $productionAfter['ventes']=$pdo->query('SELECT COUNT(*) FROM ventes')->fetchColumn();
        // Check if caisse_sessions table exists, use it instead of caisse_mouvements
        $caisseTableExists=$pdo->query("SHOW TABLES LIKE 'caisse_sessions'")->fetch();
        if($caisseTableExists){
            $productionAfter['caisse']=$pdo->query('SELECT COUNT(*) FROM caisse_sessions')->fetchColumn();
        }else{
            $productionAfter['caisse']='TABLE_NOT_FOUND';
        }
        $ecriture109=$pdo->query('SELECT * FROM ecritures_comptables WHERE id=109')->fetch(PDO::FETCH_ASSOC);
        $productionAfter['ecriture_109']=$ecriture109 ? json_encode($ecriture109) : 'NOT_FOUND';
    } catch (Exception $e) {
        $results['production']['error']='Impossible de connecter à medecin pour compteurs: '.$e->getMessage();
    }
    $results['production']['after']=$productionAfter;
    $results['production']['unchanged']=$productionBefore===$productionAfter;
    $results['production']['ecriture_109_unchanged']=$productionBefore['ecriture_109']===$productionAfter['ecriture_109'];
    
    // Non-regression tests (execute other test scripts)
    // Exclude test_access_commandes_auto.php as it's not related to Comptabilite Dashboard
    $nonRegressionScripts=[
        'validate_comptabilite_phase2.php'=>'Phase 2',
        'validate_comptabilite_phase3.php'=>'Phase 3',
        'test_dashboard_vendeur_phase2.php'=>'Vendeur',
        'test_admin_phase2.php'=>'Admin',
        'test_dashboard_assistant_final.php'=>'Assistant',
        'test_grand_livre.php'=>'Grand Livre'
    ];
    foreach($nonRegressionScripts as $script=>$name){
        $scriptPath=__DIR__.DIRECTORY_SEPARATOR.$script;
        if(!is_file($scriptPath)){
            $results['non_regression'][$name]='SCRIPT_NOT_FOUND';
            continue;
        }
        $output=shell_exec('php '.escapeshellarg($scriptPath).' 2>&1');
        $results['non_regression'][$name]=['exit_code'=>0,'output'=>$output,'pass'=>str_contains($output,'PASS')||str_contains($output,'SUCCESS')];
    }
    
    $results['limits'][]='Les contrôles RBAC VENDEUR/ASSISTANT/CHARGE_COMMANDE/ADMINISTRATEUR nécessitent leurs identifiants explicites ; aucun identifiant n’est supposé par ce script.';
    $checks=array_merge(array_column($results['routes'],'pass'),[$results['api']['read']['pass'],$results['csrf']['api_get_readonly']['pass']],array_column($results['api']['mutative_get'],'pass'),[$results['csrf']['api_without_token']['pass']],array_filter(array_column($results['rbac'],'pass'),fn($v)=>is_bool($v)));
    $nonRegressionPass=!in_array(false,array_filter(array_column($results['non_regression'],'pass'),fn($v)=>is_bool($v)),true);
    $results['summary']=['unauthenticated_pass'=>!in_array(false,array_column($results['unauthenticated'],'pass'),true),'authenticated_routes_pass'=>!in_array(false,$checks,true),'production_unchanged'=>$results['production']['unchanged'],'ecriture_109_unchanged'=>$results['production']['ecriture_109_unchanged'],'non_regression_pass'=>$nonRegressionPass];
    echo json_encode($results,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;exit($results['summary']['unauthenticated_pass']&&$results['summary']['authenticated_routes_pass']&&$results['production']['unchanged']&&$results['production']['ecriture_109_unchanged']&&$nonRegressionPass?0:1);
} finally { if(is_file($cookieFile))unlink($cookieFile); }
