import os
import hashlib
import sys


def md5(fname):
    hash_file = hashlib.md5()
    with open(fname, "rb") as f:
        for chunk in iter(lambda: f.read(1024 * 1024), b""):
            hash_file.update(chunk)
    return hash_file.hexdigest()


MC_DIR = "%s\\.minecraft" % os.environ['APPDATA']

config_hashes = []
dir_list = []

for root, subdirs, files in os.walk(MC_DIR):

    if not subdirs:
        dir_list.append('"%s"' % root.replace(MC_DIR, "").replace("\\", "\\\\"))

    if files:
        for f in files:
            full_path = "%s\\%s" % (root, f)
            config_hashes.append("\"%s\": \"%s\"" % (full_path.replace(MC_DIR, "").replace("\\", "\\\\"), md5(full_path)))

file_log_name = __file__ + ".log"
with open(file_log_name, 'w') as log_file:
    log_file.write("var DIR_LIST = [\n%s\n];\n\n" % ',\n'.join(dir_list))
    log_file.write("var FILE_HASHES = {\n%s\n};\n\n" % ',\n'.join(config_hashes))
