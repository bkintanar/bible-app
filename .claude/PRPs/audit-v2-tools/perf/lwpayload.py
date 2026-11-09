import sys,re,html,json
s=open(sys.argv[1],encoding='utf-8').read()
snap=html.unescape(re.search(r'wire:snapshot="([^"]*)"',s).group(1))
tok=re.search(r'data-csrf="([^"]+)"',s).group(1)
method=sys.argv[2]; params=json.loads(sys.argv[3])
print(json.dumps({"_token":tok,"components":[{"snapshot":snap,"updates":{},"calls":[{"path":"","method":method,"params":params}]}]}))
