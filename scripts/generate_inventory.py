import re
import pathlib
from collections import OrderedDict

ROOT = pathlib.Path(r'c:\wamp64\www\medecin')
DB_ROOT = ROOT / 'database'
OUT_PATH = DB_ROOT / 'INVENTAIRE_TABLES.md'

CREATE_TABLE_RE = re.compile(r'CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+`?([a-zA-Z0-9_]+)`?\s*\((.*?)\);', re.I | re.S)
ALTER_TABLE_RE = re.compile(r'ALTER\s+TABLE\s+`?([a-zA-Z0-9_]+)`?\s+(.+?);', re.I | re.S)
ADD_COLUMN_RE = re.compile(r'ADD\s+COLUMN(?:\s+IF\s+NOT\s+EXISTS)?\s+`?([a-zA-Z0-9_]+)`?\s+(.+?)(?=(?:,\s+ADD\s+COLUMN)|$)', re.I | re.S)
MODIFY_COLUMN_RE = re.compile(r'MODIFY\s+COLUMN\s+`?([a-zA-Z0-9_]+)`?\s+(.+?)(?=(?:,\s+ADD\s+COLUMN)|$)', re.I | re.S)


def strip_comments(text: str) -> str:
    lines = []
    for raw in text.splitlines():
        line = re.sub(r'--.*$', '', raw)
        lines.append(line)
    return '\n'.join(lines)


def normalize_type(defn: str) -> str:
    s = defn.strip()
    s = re.sub(r'\bNOT NULL\b', '', s, flags=re.I)
    s = re.sub(r'\bNULL\b', '', s, flags=re.I)
    s = re.sub(r'\bPRIMARY KEY\b', '', s, flags=re.I)
    s = re.sub(r'\bUNIQUE\b', '', s, flags=re.I)
    s = re.sub(r'\bAUTO_INCREMENT\b', '', s, flags=re.I)
    s = re.sub(r'\bDEFAULT\s+[^,\s]+', '', s, flags=re.I)
    s = re.sub(r'\bON UPDATE CURRENT_TIMESTAMP\b', '', s, flags=re.I)
    s = re.sub(r'\bCHARACTER SET[^,]+', '', s, flags=re.I)
    s = re.sub(r'\bCOLLATE[^,]+', '', s, flags=re.I)
    s = re.sub(r'\s+', ' ', s).strip().rstrip(',')
    return s


def normalize_default(defn: str):
    m = re.search(r'DEFAULT\s+([^\s,]+)', defn, flags=re.I)
    if m:
        value = m.group(1)
        if value.upper() == 'CURRENT_TIMESTAMP':
            return 'CURRENT_TIMESTAMP'
        if value.upper() == 'NULL':
            return 'NULL'
        return value
    return 'NULL' if 'NULL' in defn.upper() else '—'


def normalize_nullable(defn: str) -> str:
    return 'NO' if 'NOT NULL' in defn.upper() else 'YES'


def normalize_key(defn: str, col_name: str, fk_cols: set) -> str:
    upper = defn.upper()
    if 'PRIMARY KEY' in upper:
        return 'PRI'
    if 'UNIQUE' in upper:
        return 'UNI'
    if col_name in fk_cols or col_name.endswith('_id'):
        return 'MUL'
    return '—'


def normalize_extra(defn: str) -> str:
    upper = defn.upper()
    extra = 'auto_increment' if 'AUTO_INCREMENT' in upper else '—'
    if 'ON UPDATE CURRENT_TIMESTAMP' in upper:
        if extra == '—':
            return 'DEFAULT_GENERATED on update CURRENT_TIMESTAMP'
        return extra + ' on update CURRENT_TIMESTAMP'
    if 'DEFAULT' in upper and 'CURRENT_TIMESTAMP' in upper and extra == '—':
        return 'DEFAULT_GENERATED'
    return extra


def parse_columns_from_body(body: str):
    lines = [ln.strip() for ln in body.splitlines() if ln.strip()]
    columns = []
    fk_cols = set()
    for line in lines:
        if not line:
            continue
        if line.startswith('CONSTRAINT') or line.startswith('PRIMARY') or line.startswith('UNIQUE') or line.startswith('KEY') or line.startswith('INDEX') or line.startswith('CHECK'):
            fk_match = re.search(r'FOREIGN\s+KEY\s*\(([^)]+)\)', line, re.I)
            if fk_match:
                cols = [c.strip().strip('`') for c in fk_match.group(1).split(',')]
                fk_cols.update(cols)
            continue
        if line.startswith('FOREIGN'):
            fk_match = re.search(r'FOREIGN\s+KEY\s*\(([^)]+)\)', line, re.I)
            if fk_match:
                cols = [c.strip().strip('`') for c in fk_match.group(1).split(',')]
                fk_cols.update(cols)
            continue
        if line in {')', ');', '},'}:
            continue
        col_match = re.match(r'`?([a-zA-Z0-9_]+)`?\s+(.+)$', line)
        if not col_match:
            continue
        col_name = col_match.group(1)
        col_def = col_match.group(2).strip().rstrip(',')
        columns.append({
            'name': col_name,
            'definition': col_def,
            'type': normalize_type(col_def),
            'null': normalize_nullable(col_def),
            'key': normalize_key(col_def, col_name, fk_cols),
            'default': normalize_default(col_def),
            'extra': normalize_extra(col_def),
        })
    return columns


sql_files = sorted([p for p in DB_ROOT.rglob('*.sql') if p.is_file()])

schema_tables = OrderedDict()
for path in sql_files:
    text = path.read_text(encoding='utf-8-sig')
    clean = strip_comments(text)
    for match in CREATE_TABLE_RE.finditer(clean):
        table_name = match.group(1).lower()
        if table_name in {'sqlite_sequence'}:
            continue
        body = match.group(2)
        columns = parse_columns_from_body(body)
        schema_tables[table_name] = {'name': table_name, 'columns': columns}

    for match in ALTER_TABLE_RE.finditer(clean):
        table_name = match.group(1).lower()
        body = match.group(2)
        if table_name not in schema_tables:
            schema_tables[table_name] = {'name': table_name, 'columns': []}
        if 'ADD COLUMN' in body.upper():
            for add in ADD_COLUMN_RE.finditer(body):
                col_name = add.group(1)
                col_def = add.group(2).strip().rstrip(',')
                col_def = re.sub(r'\b(AFTER|FIRST)\s+`?[a-zA-Z0-9_]+`?$', '', col_def, flags=re.I).strip()
                columns = schema_tables[table_name]['columns']
                existing = next((c for c in columns if c['name'] == col_name), None)
                if existing:
                    existing.update({
                        'definition': col_def,
                        'type': normalize_type(col_def),
                        'null': normalize_nullable(col_def),
                        'key': normalize_key(col_def, col_name, set()),
                        'default': normalize_default(col_def),
                        'extra': normalize_extra(col_def),
                    })
                else:
                    columns.append({
                        'name': col_name,
                        'definition': col_def,
                        'type': normalize_type(col_def),
                        'null': normalize_nullable(col_def),
                        'key': normalize_key(col_def, col_name, set()),
                        'default': normalize_default(col_def),
                        'extra': normalize_extra(col_def),
                    })
        if 'MODIFY COLUMN' in body.upper():
            for mod in MODIFY_COLUMN_RE.finditer(body):
                col_name = mod.group(1)
                col_def = mod.group(2).strip().rstrip(',')
                col_def = re.sub(r'\b(AFTER|FIRST)\s+`?[a-zA-Z0-9_]+`?$', '', col_def, flags=re.I).strip()
                columns = schema_tables[table_name]['columns']
                existing = next((c for c in columns if c['name'] == col_name), None)
                if existing:
                    existing.update({
                        'definition': col_def,
                        'type': normalize_type(col_def),
                        'null': normalize_nullable(col_def),
                        'key': normalize_key(col_def, col_name, set()),
                        'default': normalize_default(col_def),
                        'extra': normalize_extra(col_def),
                    })

lines = []
lines.append('# Inventaire de la base de données medecin')
lines.append('')
lines.append(f'Généré automatiquement à partir des scripts SQL et migrations présents dans le dépôt le {ROOT.name}.')
lines.append('')
lines.append(f'Nombre de tables répertoriées : {len(schema_tables)}')
lines.append('')

for table_name, item in sorted(schema_tables.items(), key=lambda kv: kv[0]):
    columns = item['columns']
    lines.append(f'## `{table_name}` ({len(columns)} champs)')
    lines.append('')
    lines.append('| Champ | Type | Null | Clé | Défaut | Extra |')
    lines.append('|-------|------|------|-----|--------|-------|')
    for col in columns:
        lines.append(f"| `{col['name']}` | {col['type']} | {col['null']} | {col['key']} | {col['default']} | {col['extra']} |")
    lines.append('')

OUT_PATH.write_text('\n'.join(lines), encoding='utf-8')
print(f'Wrote {OUT_PATH} with {len(schema_tables)} tables')
