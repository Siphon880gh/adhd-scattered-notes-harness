# linux / server scraps

wtf does nginx -t actually validate? reload vs restart again
---
ssh keys: copy pub to authorized_keys but ALSO check permissions 700/600 or it silently fails
---
journalctl -u nginx -f   // remember -u not --unit when tired
---
https://youtu.be/dQw4w9WgXcQ
---
ufw: allow 80/443 then enable; don't lock yourself out of 22
---
certbot certonly --nginx -d example.com
renewal is a cron/timer thing — check with systemctl list-timers | grep cert
