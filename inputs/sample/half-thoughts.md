rsync -avz --delete ./dist/ user@host:/var/www/app/
DRY RUN FIRST with -n

tmux: Ctrl-b d detach, Ctrl-b c new window, Ctrl-b % split

grep -R "TODO" --exclude-dir=node_modules .

article idea?: "permissions that look fine but break ssh/nginx" — 700 home, 700 .ssh, 600 keys, SELinux/AppArmor footnote

pg: dump with pg_dump -Fc, restore pg_restore -d ... don't use plain sql for big dbs
