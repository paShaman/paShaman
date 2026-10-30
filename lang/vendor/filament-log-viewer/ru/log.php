<?php

return [
    'placeholder' => 'Н/Д',
    'navigation' => [
        'title' => 'Логи',
        'heading' => 'Журнал логов',
        'subheading' => '',
        'group' => 'Система',
        'label' => 'Логи',
    ],
    'table' => [
        'model_label' => 'лог',
        'plural_model_label' => 'логи',
        'columns' => [
            'log_level' => 'Уровень',
            'env' => 'Окружение',
            'file' => 'Файл',
            'message' => 'Сообщение',
            'date' => 'Когда',
        ],
        'filters' => [
            'env' => [
                'label' => 'Окружение',
                'indicator' => 'Фильтр по окружению',
            ],
            'file' => [
                'label' => 'Файл',
                'indicator' => 'Фильтр по файлу',
            ],
            'date' => [
                'label' => 'Дата',
                'indicator' => 'Фильтр по дате',
                'from' => 'С',
                'until' => 'По',
            ],
            'date_range' => [
                'label' => 'Диапазон дат',
                'indicator' => 'Фильтр по диапазону дат',
            ],
            'indicators' => [
                'logs_from_to' => 'Логи с :from по :until',
                'logs_from' => 'Логи с :from',
                'logs_until' => 'Логи по :until',
            ],
        ],
        'actions' => [
            'view' => [
                'label' => 'Просмотр',
                'heading' => 'Лог ошибки',
            ],
            'read' => [
                'label' => 'Прочитать письмо',
                'subject' => 'Тема',
                'mail_log' => 'Лог писем',
                'sent_date' => 'Дата отправки',
            ],
            'refresh' => [
                'label' => 'Обновить',
            ],
            'clear' => [
                'label' => 'Очистить логи',
                'success' => 'Все логи удалены!',
            ],
            'copy_markdown' => [
                'label' => 'Скопировать как Markdown',
                'success' => 'Markdown скопирован в буфер обмена',
                'headers' => [
                    'file' => 'Файл',
                    'message' => 'Сообщение',
                    'description' => 'Описание',
                    'context' => 'Контекст',
                    'stack_trace' => 'Стек вызовов',
                    'mail' => 'Данные письма',
                ],
            ],
        ],
    ],
    'schema' => [
        'error-log' => [
            'stack' => 'Стек вызовов',
        ],
        'json-log' => [
            'context' => 'Контекст',
        ],
    ],
    'mail' => [
        'sender' => [
            'label' => 'Отправитель',
            'name' => 'Имя',
            'email' => 'Email',
        ],
        'receiver' => [
            'label' => 'Получатель',
            'name' => 'Имя',
            'email' => 'Email',
        ],
        'content' => 'Содержимое',
        'plain' => 'Обычный текст',
        'html' => 'HTML',
    ],
    'levels' => [
        'all' => 'Все логи',
        'alert' => 'Тревога',
        'critical' => 'Критично',
        'debug' => 'Отладка',
        'emergency' => 'Авария',
        'error' => 'Ошибка',
        'info' => 'Инфо',
        'notice' => 'Заметка',
        'warning' => 'Предупреждение',
        'mail' => 'Письма',
    ],
];
