import sys, re, json, html
def walk_xml(o):
    t=0
    if isinstance(o, dict):
        for k,v in o.items():
            if k=='original_xml' and isinstance(v,str): t+=len(json.dumps(v))
            else: t+=walk_xml(v)
    elif isinstance(o, list):
        for v in o: t+=walk_xml(v)
    return t
def count_key(o,key):
    c=0
    if isinstance(o, dict):
        for k,v in o.items():
            if k==key: c+=1
            c+=count_key(v,key)
    elif isinstance(o,list):
        for v in o: c+=count_key(v,key)
    return c
for f in sys.argv[1:]:
    s=open(f,encoding='utf-8').read()
    total=len(s.encode())
    snaps=re.findall(r'wire:snapshot="([^"]*)"', s)
    print(f"== {f}: page {total} B, {len(snaps)} snapshot(s)")
    for raw in snaps:
        dec=html.unescape(raw)
        j=json.loads(dec)
        print(f"  snapshot attr {len(raw.encode())} B (escaped) / {len(dec.encode())} B decoded = {100*len(raw.encode())/total:.1f}% of page; component={j['memo'].get('name')}")
        data=j['data']
        rows=[]
        for k,v in data.items():
            b=len(json.dumps(v,separators=(',',':'),ensure_ascii=False).encode())
            rows.append((b,k,walk_xml(v)))
        rows.sort(reverse=True)
        for b,k,x in rows:
            if b>50: print(f"    {k:24s} {b:8d} B  original_xml={x}")
        print(f"    original_xml total: {walk_xml(data)} B decoded ({count_key(data,'original_xml')} occurrences) = {100*walk_xml(data)/len(dec.encode()):.1f}% of decoded snapshot")
        print(f"    combined_text occurrences: {count_key(data,'combined_text')}, bytes: ", end='')
        def ct(o):
            t=0
            if isinstance(o,dict):
                for k,v in o.items():
                    t+= len(json.dumps(v)) if k=='combined_text' else ct(v)
            elif isinstance(o,list):
                for v in o: t+=ct(v)
            return t
        print(ct(data))
    # escaped-html overhead: count &quot;
    print(f"  wire:effects count={len(re.findall(r'wire:effects', s))}; occurrences of 'original_xml' in raw page: {s.count('original_xml')}")
