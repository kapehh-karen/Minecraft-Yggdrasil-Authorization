import os


APP_DATA = r"C:\Users\XXX\AppData\Roaming"

res = []
for root, subdirs, files in os.walk(r"%s\.minecraft\libraries" % APP_DATA):
    if files:
        #print("%s || %s || %s" % (root, subdirs, files))
        res.append(root.replace(APP_DATA, r"%APPDATA%") + "\\" + files[0])

print(';'.join(res))
