# ERP Oficina — Backend (Laravel 13 + MySQL)

API REST do ERP. O front React (pasta acima) conversa com ela em `/api`.

## Instalação (primeira vez)

1. **Instale o MySQL 8** (MySQL Installer, XAMPP ou Laragon) e crie o banco:
   ```sql
   CREATE DATABASE erp_oficina CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
2. Ajuste usuário/senha do MySQL no arquivo `.env` (`DB_USERNAME`, `DB_PASSWORD`).
3. Defina a senha do administrador no `.env` (`ADMIN_PASSWORD=`). Se deixar vazio, uma senha é gerada e mostrada uma única vez.
4. Crie as tabelas e o primeiro usuário:
   ```
   php artisan migrate --seed
   ```
   Em `APP_ENV=local` também são criados dados de exemplo e dois usuários de teste:
   `atendente@oficina.local` e `mecanico@oficina.local` (senha `Senha123`, **só para desenvolvimento**).

## Rodando

Dê dois cliques em **`iniciar-backend.bat`** — ele abre 3 janelas:

| Janela | Comando | Para quê |
|---|---|---|
| API | `php artisan serve` | responde o front em http://localhost:8000 |
| Fila de jobs | `php artisan queue:work` | envia e-mails/WhatsApp, gera PDFs, relatórios e backups |
| Agendador | `php artisan schedule:work` | dispara as rotinas diárias nos horários |

Depois rode o front normalmente (`npm run dev` na pasta de cima). O Vite já encaminha `/api` para a porta 8000.

> Em produção, no lugar do `schedule:work`, crie no **Agendador de Tarefas do Windows** uma tarefa que roda
> `php artisan schedule:run` a cada minuto, e mantenha o `queue:work` como serviço (ex.: NSSM).

## Jobs (tarefas em segundo plano)

| Job | Quando | O que faz |
|---|---|---|
| `ProcessarEnvio` | ao clicar "enviar" | gera o PDF e envia por e-mail ou WhatsApp; 3 tentativas; registra em `envios` |
| `GerarRelatorio` | ao pedir um relatório | gera PDF ou Excel (financeiro, OS, orçamentos, comissões, estoque) |
| `ExpirarOrcamentos` | 00:10 | marca orçamentos vencidos e avisa os que vencem amanhã |
| `GerarBackup` | 02:00 | `.sql.gz` em `storage/app/private/backups` (mantém os 14 últimos) |
| `LimparArquivosAntigos` | 03:00 | apaga relatórios com mais de 7 dias |
| `AlertarContasVencidas` | 07:00 | resumo de contas vencidas/do dia para a equipe |
| `AlertarEstoqueBaixo` | 07:05 e na baixa de OS | peças abaixo do mínimo |
| `EnviarLembretesAgendamento` | 18:00 | lembrete para os clientes agendados no dia seguinte |

Para testar uma rotina sem esperar o horário: `php artisan oficina:rotina contas` (ou `expirar`, `backup`, `limpar`, `estoque`, `lembretes`).

Os alertas aparecem no sininho do sistema (admin e atendente) e chegam por e-mail para os administradores.

## E-mail

Em desenvolvimento (`MAIL_MAILER=log`) os e-mails são gravados em `storage/logs/laravel.log`.
Para enviar de verdade, configure um SMTP no `.env` (Gmail com senha de app, Brevo, Amazon SES etc.):
```
MAIL_MAILER=smtp
MAIL_HOST=smtp.seuprovedor.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=contato@suaoficina.com.br
```

## WhatsApp

Usa a **WhatsApp Business Cloud API** da Meta. É preciso uma conta Meta Business com um número verificado.
1. Em developers.facebook.com crie um app do tipo *Business* e adicione o produto WhatsApp.
2. Gere um **token permanente** (usuário do sistema) e copie o **Phone number ID**.
3. Preencha no `.env`: `WHATSAPP_TOKEN` e `WHATSAPP_PHONE_NUMBER_ID`.
4. A Meta só permite mensagem livre até 24h depois que o cliente falou com você. Para enviar a qualquer momento,
   aprove templates e informe os nomes em `WHATSAPP_TEMPLATE_DOCUMENTO` (cabeçalho do tipo documento + 3 variáveis:
   nome, número, total) e `WHATSAPP_TEMPLATE_LEMBRETE` (3 variáveis: nome, data, hora).

O token fica **só no servidor**; o front apenas recebe `whatsappConfigurado: true/false`.

## Perfis de acesso

| Perfil | Acesso |
|---|---|
| Administrador | tudo, incluindo usuários, configurações, logo, backups e relatório de comissões |
| Atendente | clientes, veículos, orçamentos, OS, agenda, estoque, financeiro e relatórios |
| Mecânico | somente as OS atribuídas a ele: ver, registrar diagnóstico e mudar status (iniciar, aguardando peça, concluir) |

## Testes

```
php artisan test
```
Rodam em SQLite em memória (não mexem no MySQL).

## Principais rotas da API

Todas as rotas exigem login (cookie de sessão via Sanctum), exceto `POST /api/login`.
Os campos usam camelCase (ex.: `clienteId`), igual ao front.

```
POST   /api/login  /api/logout      GET /api/me
GET    /api/dashboard
CRUD   /api/clientes /api/veiculos /api/servicos /api/pecas /api/funcionarios /api/usuarios
POST   /api/pecas/{id}/entrada
CRUD   /api/orcamentos     POST {id}/status {id}/converter {id}/duplicar {id}/enviar   GET {id}/pdf
CRUD   /api/ordens         POST {id}/status {id}/faturar {id}/enviar                   GET {id}/pdf
GET    /api/envios         POST /api/envios/{id}/reenviar
CRUD   /api/lancamentos    POST {id}/baixar
CRUD   /api/agendamentos   GET /api/agendamentos/conflito
GET/PUT /api/configuracoes POST/DELETE /api/configuracoes/logo
GET/POST /api/relatorios   GET /api/relatorios/{id}/download
GET/POST /api/backups      GET /api/backups/{nome}
GET    /api/notificacoes   POST /api/notificacoes/lidas
```
Lista completa: `php artisan route:list --path=api`.
