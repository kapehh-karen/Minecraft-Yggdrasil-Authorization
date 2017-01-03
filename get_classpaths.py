import os


APP_DATA = os.environ['APPDATA']

res = []
for root, subdirs, files in os.walk(r"%s\.minecraft\libraries" % APP_DATA):
    if files:
        for f in files:
            full_path = "%s\\%s" % (root.replace(APP_DATA, r"%APPDATA%"), f)
            res.append(full_path)

print(';'.join(res))
