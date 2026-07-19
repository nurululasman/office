<?php

return [
    'backup_evidence_path' => env('OFFICE_BACKUP_EVIDENCE_PATH', storage_path('app/operations/backup-status.json')),
    'restore_evidence_path' => env('OFFICE_RESTORE_EVIDENCE_PATH', storage_path('app/operations/restore-drill.json')),
    'uat_evidence_path' => env('OFFICE_UAT_EVIDENCE_PATH', storage_path('app/operations/uat-signoff.json')),
    'cutover_manifest_path' => env('OFFICE_CUTOVER_MANIFEST_PATH', storage_path('app/operations/initial-data-cutover.json')),
    'database_backup_max_age_hours' => (int) env('OFFICE_DATABASE_BACKUP_MAX_AGE_HOURS', 26),
    'wal_archive_max_age_minutes' => (int) env('OFFICE_WAL_ARCHIVE_MAX_AGE_MINUTES', 60),
    'private_files_max_age_minutes' => (int) env('OFFICE_PRIVATE_FILES_BACKUP_MAX_AGE_MINUTES', 60),
    'restore_drill_max_age_days' => (int) env('OFFICE_RESTORE_DRILL_MAX_AGE_DAYS', 93),
    'queue_pending_alert' => (int) env('OFFICE_QUEUE_PENDING_ALERT', 100),
    'queue_failed_alert' => (int) env('OFFICE_QUEUE_FAILED_ALERT', 1),
];
