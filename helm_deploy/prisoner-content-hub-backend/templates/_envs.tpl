{{/*
Environment variables for Drupal & Drush containers
*/}}
{{- define "drupal-deployment.envs" }}
env:
  - name: HUB_DB_ENV_MYSQL_DATABASE
    valueFrom:
      secretKeyRef:
        name: {{ .Values.application.dbSecretName }}
        key: database_name
  - name: HUB_DB_ENV_MYSQL_USER
    valueFrom:
      secretKeyRef:
        name: {{ .Values.application.dbSecretName }}
        key: database_username
  - name: HUB_DB_ENV_MYSQL_PASSWORD
    valueFrom:
      secretKeyRef:
        name: {{ .Values.application.dbSecretName }}
        key: database_password
  - name: HUB_DB_PORT_3306_TCP_ADDR
    valueFrom:
      secretKeyRef:
        name: {{ .Values.application.dbSecretName }}
        key: rds_instance_address
  - name: FLYSYSTEM_S3_KEY
    valueFrom:
      secretKeyRef:
        name: {{ .Values.application.s3.secretName }}
        key: access_key_id
  - name: FLYSYSTEM_S3_SECRET
    valueFrom:
      secretKeyRef:
        name: {{ .Values.application.s3.secretName }}
        key: secret_access_key
  - name: FLYSYSTEM_S3_REGION
    value: {{ .Values.application.s3.region }}
  - name: FLYSYSTEM_S3_BUCKET
    valueFrom:
      secretKeyRef:
        name: {{ .Values.application.s3.secretName }}
        key: bucket_name
  - name: FLYSYSTEM_S3_CNAME
    value: {{ .Values.application.s3.cname }}
  - name: FLYSYSTEM_S3_CNAME_IS_BUCKET
    value: {{ .Values.application.s3.cnameIsBucket | quote }}
  - name: HASH_SALT
    valueFrom:
      secretKeyRef:
        name: {{ include "prisoner-content-hub-backend.fullname" . }}
        key: hashSalt
  - name: FILE_PUBLIC_BASE_URL
    value: {{ include "prisoner-content-hub-backend.externalHost" . }}/sites/default/files
  - name: PHP_MEMORY_LIMIT
    value: {{ .Values.application.config.phpMemoryLimit }}
  - name: PHP_UPLOAD_MAX_FILE_SIZE
    value: {{ .Values.application.config.phpUploadMaxFileSize }}
  - name: PHP_POST_MAX_SIZE
    value: {{ .Values.application.config.phpPostMaxSize }}
  - name: XDEBUG_IP
    value: {{ .Values.application.config.xDebugIp }}
  - name: SERVER_PORT
    value: {{ .Values.application.port | quote }}
  - name: ELASTICSEARCH_CLUSTER
    value: {{ .Values.application.config.elasticsearchCluster }}
  - name: ELASTICSEARCH_HOST
    value: {{ include "prisoner-content-hub-backend.elasticsearchServiceHost" . }}
  - name: SENTRY_DSN
    value: {{ .Values.application.sentry_dsn }}
  - name: SENTRY_ENVIRONMENT
    value: {{ .Values.application.sentry_environment }}
  - name: SENTRY_RELEASE
    value: {{ quote .Values.application.sentry_release }}
  - name: TRUSTED_HOSTS
    value: {{ include "prisoner-content-hub-backend.trustedHosts" . }}
  - name: TRUSTED_HOSTS_JSONAPI
    value: {{ include "prisoner-content-hub-backend.trustedHostsJsonApi" . }}
  - name: REDIS_HOST
    valueFrom:
      secretKeyRef:
        name: {{ .Values.application.redisSecretName }}
        key: primary_endpoint_address
  - name: REDIS_PASSWORD
    valueFrom:
      secretKeyRef:
        name: {{ .Values.application.redisSecretName }}
        key: auth_token
  - name: REDIS_TLS_ENABLED
    value: "true"
  - name: GOVUK_NOTIFY_API_KEY
    valueFrom:
      secretKeyRef:
        name: {{ .Values.application.govUkSecretName }}
        key: access_key

{{- end -}}

{{- define "db-backup.envs" }}
env:
  - name: HUB_DB_ENV_MYSQL_DATABASE
    valueFrom:
      secretKeyRef:
        name: {{ .Values.application.dbSecretName }}
        key: database_name
  - name: HUB_DB_ENV_MYSQL_USER
    valueFrom:
      secretKeyRef:
        name: {{ .Values.application.dbSecretName }}
        key: database_username
  - name: HUB_DB_ENV_MYSQL_PASSWORD
    valueFrom:
      secretKeyRef:
        name: {{ .Values.application.dbSecretName }}
        key: database_password
  - name: HUB_DB_PORT_3306_TCP_ADDR
    valueFrom:
      secretKeyRef:
        name: {{ .Values.application.dbSecretName }}
        key: rds_instance_address
  - name: DB_BACKUP_S3_KEY
    valueFrom:
      secretKeyRef:
        name: db-backups-s3
        key: access_key_id
  - name: DB_BACKUP_S3_SECRET
    valueFrom:
      secretKeyRef:
        name: db-backups-s3
        key: secret_access_key
  - name: DB_BACKUP_S3_REGION
    value: {{ .Values.dbBackup.s3.region }}
  - name: DB_BACKUP_S3_BUCKET
    valueFrom:
      secretKeyRef:
        name: db-backups-s3
        key: bucket_name
{{- end -}}

{{- define "s3-sync.envs" }}
env:
  - name: S3_DESTINATION_KEY
    valueFrom:
      secretKeyRef:
        name: {{ .Values.application.s3.secretName }}
        key: access_key_id
  - name: S3_DESTINATION_SECRET
    valueFrom:
      secretKeyRef:
        name: {{ .Values.application.s3.secretName }}
        key: secret_access_key
  - name: S3_DESTINATION_REGION
    value: {{ .Values.application.s3.region }}
  - name: S3_DESTINATION_BUCKET
    valueFrom:
      secretKeyRef:
        name: {{ .Values.application.s3.secretName }}
        key: bucket_name
  - name: S3_SOURCE_BUCKET
    valueFrom:
      secretKeyRef:
        name: drupal-s3-output-new
        key: bucket_name
  - name: S3_SOURCE_REGION
    value: {{ .Values.s3Sync.source_region }}
{{- end -}}

{{- define "s3-sync-temp.envs" }}
env:
  - name: S3_DESTINATION_KEY_TEMP
    valueFrom:
      secretKeyRef:
        name: {{ .Values.application.s3temp.secretName }}
        key: access_key_id
  - name: S3_DESTINATION_SECRET_TEMP
    valueFrom:
      secretKeyRef:
        name: {{ .Values.application.s3temp.secretName }}
        key: secret_access_key
  - name: S3_DESTINATION_REGION_TEMP
    value: {{ .Values.application.s3temp.region }}
  - name: S3_DESTINATION_BUCKET_TEMP
    valueFrom:
      secretKeyRef:
        name: {{ .Values.application.s3temp.secretName }}
        key: bucket_name
  - name: S3_SOURCE_BUCKET_TEMP
    valueFrom:
      secretKeyRef:
        name: drupal-s3-output
        key: bucket_name
  - name: S3_SOURCE_REGION_TEMP
    value: {{ .Values.s3SyncTemp.source_region }}
{{- end -}}
