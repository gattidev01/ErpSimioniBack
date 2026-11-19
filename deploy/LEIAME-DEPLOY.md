# Colocando o ERP no ar (VPS Optima Server, Ubuntu)

Tempo estimado: 20 a 30 minutos. Tudo é feito pelo **PowerShell do seu Windows** + **painel do domínio** + **GitHub**.

No exemplo abaixo, troque:
- `IP_DA_VPS` pelo IP da VPS (está no painel da Optima Server);
- `erp.seudominio.com.br` pelo subdomínio escolhido;
- `seu-email@exemplo.com` pelo seu e-mail.

---

## 1. Apontar o subdomínio para a VPS (faça primeiro: leva alguns minutos para propagar)

No painel onde o domínio está registrado (Registro.br, Hostinger, GoDaddy, Cloudflare...), crie um registro DNS:

| Tipo | Nome | Valor | TTL |
|---|---|---|---|
| **A** | `erp` (a parte antes do domínio) | `IP_DA_VPS` | 3600 (ou o padrão) |

> Usa Cloudflare? Deixe a nuvem **cinza** (somente DNS) durante a instalação, para o certificado HTTPS ser emitido.

Para conferir se já propagou, no PowerShell:
```powershell
Resolve-DnsName erp.seudominio.com.br
```
Deve aparecer o IP da VPS.

## 2. Enviar o script para a VPS

No PowerShell, dentro da pasta do projeto:
```powershell
cd C:\Users\SANSUNG\Desktop\erpsimioni\backend\deploy
scp .\instalar-servidor.sh root@IP_DA_VPS:/root/
```
Na primeira vez ele pergunta se confia no servidor: digite `yes`. Depois, a senha do root da VPS (vem no e-mail/painel da Optima).

## 3. Entrar na VPS e rodar a instalação

```powershell
ssh root@IP_DA_VPS
```
Já dentro da VPS:
```bash
bash /root/instalar-servidor.sh erp.seudominio.com.br seu-email@exemplo.com
```

O script instala tudo sozinho. **Ele vai parar uma vez** e mostrar duas chaves (começam com `ssh-ed25519`):

1. Abra **github.com/gattidev01/ErpSimioniBack → Settings → Deploy keys → Add deploy key**
   - Title: `VPS ERP` · Key: cole a **primeira** chave · **não** marque *Allow write access* → *Add key*
2. Abra **github.com/gattidev01/ErpSimioni → Settings → Deploy keys → Add deploy key**
   - Title: `VPS ERP` · Key: cole a **segunda** chave · **não** marque *Allow write access* → *Add key*
3. Volte ao terminal e aperte **ENTER**.

> As chaves só dão permissão de **leitura**, e cada uma só de um repositório. A VPS consegue baixar o código, mas não alterar nada no GitHub.

No final aparece:
```
Endereço:  https://erp.seudominio.com.br
Login do administrador:  admin@seudominio.com.br
Senha (ANOTE AGORA, não será mostrada de novo):  ...
```
**Anote a senha.** Entre no sistema e cadastre os usuários da equipe em *Configurações → Usuários*.

Se aparecer o aviso de que o DNS ainda não aponta para a VPS, espere a propagação (passo 1) e rode o comando de HTTPS que o próprio aviso mostra.

## 4. Configurar o envio de e-mail (para orçamentos e alertas)

Ainda na VPS:
```bash
nano /var/www/erp/back/.env
```
Altere as linhas `MAIL_` com os dados do seu provedor (Gmail com senha de app, Hostinger, Zoho, Brevo...):
```
MAIL_MAILER=smtp
MAIL_HOST=smtp.seuprovedor.com
MAIL_PORT=587
MAIL_USERNAME=contato@seudominio.com.br
MAIL_PASSWORD=sua-senha-de-app
MAIL_FROM_ADDRESS="contato@seudominio.com.br"
```
Salve (`Ctrl+O`, `Enter`, `Ctrl+X`) e aplique:
```bash
bash /var/www/erp/back/deploy/atualizar.sh
```
O WhatsApp é configurado do mesmo jeito (`WHATSAPP_TOKEN` e `WHATSAPP_PHONE_NUMBER_ID`). Veja o `LEIAME.md` do backend.

---

## Publicar uma nova versão

Depois de fazer `git push` das mudanças (front e/ou back), na VPS:
```bash
ssh root@IP_DA_VPS
bash /var/www/erp/back/deploy/atualizar.sh
```
Ele baixa o código, atualiza o banco (migrations), recompila o front e reinicia a fila, sem derrubar o sistema.

## O que fica rodando na VPS

| Peça | Onde | Ver / reiniciar |
|---|---|---|
| Site + API (Nginx) | `/etc/nginx/sites-available/erp` | `systemctl status nginx` |
| PHP | PHP-FPM 8.4 | `systemctl restart php8.4-fpm` |
| Banco | MySQL, banco `erp_oficina` | `mysql erp_oficina` |
| Fila de jobs | Supervisor `erp-fila` | `supervisorctl status` · log em `/var/log/erp-fila.log` |
| Rotinas diárias | `/etc/cron.d/erp-oficina` | rodam a cada minuto via `schedule:run` |
| HTTPS | Certbot (renova sozinho) | `certbot renew --dry-run` |
| Erros do sistema | `/var/www/erp/back/storage/logs/laravel.log` | `tail -f` no arquivo |

## Backups

O sistema faz backup do banco **todo dia às 02:00** em `/var/www/erp/back/storage/app/private/backups` (guarda os 14 últimos) e você pode baixar pela tela *Configurações → Backups*.

**Importante:** esses arquivos ficam na própria VPS. Baixe um de vez em quando (ou ative o backup/snapshot da Optima Server), para não perder tudo se a VPS tiver problema.

## Segurança já configurada

- Firewall liberando só SSH, HTTP e HTTPS.
- HTTPS obrigatório (Let's Encrypt), cookie de sessão `Secure` + `HttpOnly`.
- Cabeçalhos CSP, HSTS, X-Frame-Options, nosniff, Referrer-Policy e Permissions-Policy.
- `.env` legível apenas pelo servidor web; senha do banco aleatória; `APP_DEBUG=false`.
- Recomendado: no painel da Optima, troque a senha do root por uma forte (ou use chave SSH).
