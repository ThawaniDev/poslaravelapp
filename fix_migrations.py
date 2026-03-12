#!/usr/bin/env python3
"""Fix raw SQL migrations to skip when running on SQLite (for testing)."""
import os
import re
import glob

base_dir = '/Users/dogorshom/Desktop/Thawani/thawani/POS/poslaravelapp/database/migrations/'
pattern = re.compile(r'(public function up\(\): void\s*\n\s*\{)', re.MULTILINE)
sqlite_guard = "\n        if (\\Illuminate\\Support\\Facades\\Schema::getConnection()->getDriverName() === 'sqlite') {\n            return;\n        }\n"

fixed = 0
already_fixed = 0
no_raw_sql = 0

for filepath in sorted(glob.glob(base_dir + '2026_03_10_040*.php')):
    with open(filepath) as f:
        content = f.read()
    has_raw = 'DB::unprepared' in content or 'DB::statement' in content
    has_guard = 'sqlite' in content
    bn = os.path.basename(filepath)
    if has_raw and not has_guard:
        m = pattern.search(content)
        if m:
            insert_pos = m.end()
            new_content = content[:insert_pos] + sqlite_guard + content[insert_pos:]
            with open(filepath, 'w') as f:
                f.write(new_content)
            fixed += 1
            print('FIXED: ' + bn)
        else:
            print('NO MATCH: ' + bn)
    elif has_raw and has_guard:
        already_fixed += 1
    else:
        no_raw_sql += 1

print('')
print('Needed fix: ' + str(fixed) + ', Already fixed: ' + str(already_fixed) + ', No raw SQL: ' + str(no_raw_sql))
