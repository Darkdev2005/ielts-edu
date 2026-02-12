<?php

return [
    'provider' => env('AI_PROVIDER', 'gemini'),
    'task_providers' => [
        'writing_feedback' => env('AI_WRITING_PROVIDER'),
        'writing_followup' => env('AI_WRITING_PROVIDER'),
    ],
    'writing_practice_driver' => env('AI_WRITING_PRACTICE_DRIVER', 'ai'),
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'api_keys' => array_values(array_filter(array_map('trim', explode(',', (string) env('GEMINI_API_KEYS', ''))))),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'max_output_tokens' => (int) env('AI_MAX_OUTPUT_TOKENS', 1024),
        'temperature' => (float) env('AI_TEMPERATURE', 0.4),
        'verify_ssl' => env('AI_VERIFY_SSL', true),
    ],
    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'llama-3.1-8b-instant'),
        'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
        'max_output_tokens' => (int) env('AI_MAX_OUTPUT_TOKENS', 1024),
        'temperature' => (float) env('AI_TEMPERATURE', 0.4),
        'verify_ssl' => env('AI_VERIFY_SSL', true),
    ],
    'dedup_ttl_minutes' => (int) env('AI_DEDUP_TTL_MINUTES', 60),
    'dedup_exclude_tasks' => array_values(array_filter(array_map(
        static fn ($task) => trim((string) $task),
        explode(',', (string) env('AI_DEDUP_EXCLUDE_TASKS', 'writing_feedback,speaking_feedback,writing_followup'))
    ))),
    'user_rpm' => (int) env('AI_USER_RPM', 20),
    'global_rpm' => (int) env('AI_GLOBAL_RPM', 200),
    'max_concurrency' => (int) env('AI_MAX_CONCURRENCY', 5),
    'provider_cooldown_seconds' => (int) env('AI_PROVIDER_COOLDOWN_SECONDS', 60),
    'request_timeout_seconds' => 30,
    'free_max_output_tokens' => (int) env('AI_FREE_MAX_OUTPUT_TOKENS', 150),
    'free_writing_max_output_tokens' => (int) env('AI_FREE_WRITING_MAX_OUTPUT_TOKENS', 800),
    'free_speaking_max_output_tokens' => (int) env('AI_FREE_SPEAKING_MAX_OUTPUT_TOKENS', 600),
    'max_pending_seconds' => (int) env('AI_MAX_PENDING_SECONDS', 120),
];
