import re, glob

test_files = glob.glob('tests/**/*.php', recursive=True)

for fp in test_files:
    with open(fp, 'r') as f:
        content = f.read()
    
    original = content
    
    def fix_org_create(match):
        block = match.group(0)
        lines = block.split('\n')
        fixed = []
        for line in lines:
            if re.search(r"'currency'\s*=>", line):
                continue
            fixed.append(line)
        return '\n'.join(fixed)
    
    def fix_store_create(match):
        block = match.group(0)
        lines = block.split('\n')
        fixed = []
        for line in lines:
            if re.search(r"'country'\s*=>", line):
                continue
            fixed.append(line)
        return '\n'.join(fixed)
    
    content = re.sub(
        r'Organization::create\(\[.*?\]\)',
        fix_org_create,
        content,
        flags=re.DOTALL
    )
    
    content = re.sub(
        r'Store::create\(\[.*?\]\)',
        fix_store_create,
        content,
        flags=re.DOTALL
    )
    
    if content != original:
        with open(fp, 'w') as f:
            f.write(content)
        print(f"Fixed: {fp}")

print("Done!")
