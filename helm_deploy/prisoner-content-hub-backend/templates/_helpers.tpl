{{/* vim: set filetype=mustache: */}}
{{/*
Expand the name of the chart.
*/}}
{{- define "prisoner-content-hub-backend.name" -}}
{{- default .Chart.Name .Values.nameOverride | trunc 63 | trimSuffix "-" }}
{{- end }}

{{/*
Create a default fully qualified app name.
We truncate at 63 chars because some Kubernetes name fields are limited to this (by the DNS naming spec).
If release name contains chart name it will be used as a full name.
*/}}
{{- define "prisoner-content-hub-backend.fullname" -}}
{{ if .Values.fullnameOverride -}}
{{ .Values.fullnameOverride | trunc 63 | trimSuffix "-" }}
{{- else -}}
{{ .Release.Name | trunc 63 | trimSuffix "-" }}
{{- end }}
{{- end }}

{{/*
Create chart name and version as used by the chart label.
*/}}
{{- define "prisoner-content-hub-backend.chart" -}}
{{- printf "%s-%s" .Chart.Name .Chart.Version | replace "+" "_" | trunc 63 | trimSuffix "-" }}
{{- end }}

{{/*
Common labels
*/}}
{{- define "prisoner-content-hub-backend.labels" -}}
chart: {{ include "prisoner-content-hub-backend.chart" . }}
{{ include "prisoner-content-hub-backend.selectorLabels" . }}
heritage: {{ .Release.Service }}
{{- end }}

{{/*
Common labels
*/}}
{{- define "drupal.labels" -}}
chart: {{ include "prisoner-content-hub-backend.chart" . }}
{{ include "drupal.selectorLabels" . }}
heritage: {{ .Release.Service }}
{{- end }}

{{/*
Selector labels
*/}}
{{- define "prisoner-content-hub-backend.selectorLabels" -}}
app: {{ include "prisoner-content-hub-backend.name" . }}
release: {{ .Release.Name }}
tier: {{ .Values.tier }}
{{- end }}

{{/*
Selector labels
*/}}
{{- define "drupal.selectorLabels" -}}
app: {{ include "prisoner-content-hub-backend.name" . }}-drupal
release: {{ .Release.Name }}
tier: {{ .Values.tier }}
{{- end }}

{{/*
Create internal Kubernetes hostname
*/}}
{{- define "prisoner-content-hub-backend.internalHost" -}}
{{- printf "http://%s.%s.svc.cluster.local" (include "prisoner-content-hub-backend.fullname" .) .Release.Namespace }}
{{- end }}

{{/*
Create internal Kubernetes hostname
*/}}
{{- define "prisoner-content-hub-backend.elasticsearchServiceHost" -}}
{{- printf "http://%s.%s.svc.cluster.local:9200" .Values.application.config.elasticsearchServiceName .Release.Namespace }}
{{- end }}

{{/*
Create external Kubernetes hostname
*/}}
{{- define "prisoner-content-hub-backend.externalHost" -}}
{{- $protocol := "http" }}
{{- if .Values.ingress.tlsEnabled }}
{{- $protocol = "https" }}
{{- end }}
{{- printf "%s://%s" $protocol (index .Values.ingress.hosts 0).host }}
{{- end }}

{{/*
Create trusted host pattern
*/}}
{{- define "prisoner-content-hub-backend.trustedHosts" -}}
{{- with (first .Values.ingress.hosts) -}}
^{{ (.host | replace "." "\\.") }}$
{{- end }}
{{- range (slice .Values.ingress.hosts 1) -}}
|^{{ (.host | replace "." "\\.")}}$
{{- end }}
{{- printf "|^%s\\.%s\\.svc\\.cluster\\.local$" (include "prisoner-content-hub-backend.fullname" .) .Release.Namespace }}
{{- end }}

{{/*
Create trusted jsonapi host pattern
*/}}
{{- define "prisoner-content-hub-backend.trustedHostsJsonApi" -}}
{{- with (first .Values.ingress.jsonapi.hosts) -}}
^{{ (.host | replace "." "\\.") }}$
{{- end }}
{{- range (slice .Values.ingress.jsonapi.hosts 1) -}}
|^{{ (.host | replace "." "\\.")}}$
{{- end }}
{{- printf "|^%s\\.%s\\.svc\\.cluster\\.local$" (include "prisoner-content-hub-backend.fullname" .) .Release.Namespace }}
{{- end }}

{{/*
Create a string from a list of values joined by a comma
*/}}
{{- define "app.joinListWithComma" -}}
{{- $local := dict "first" true -}}
{{- range $k, $v := . -}}{{- if not $local.first -}},{{- end -}}{{- $v -}}{{- $_ := set $local "first" false -}}{{- end -}}
{{- end -}}
