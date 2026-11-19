<?php

return [
    // Endereço do front (usado nos links dos e-mails de alerta)
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),

    // Backups automáticos
    'backup_horario' => env('BACKUP_HORARIO', '02:00'),
    'backup_manter' => (int) env('BACKUP_MANTER', 14),

    // Relatórios gerados ficam disponíveis por N dias
    'relatorios_dias' => (int) env('RELATORIOS_DIAS', 7),

    // Horário em que os lembretes do dia seguinte são enviados
    'lembrete_horario' => env('LEMBRETE_HORARIO', '18:00'),
];
