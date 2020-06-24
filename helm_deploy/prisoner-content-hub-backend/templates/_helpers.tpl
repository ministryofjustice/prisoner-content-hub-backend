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
Selector labels
*/}}
{{- define "prisoner-content-hub-backend.selectorLabels" -}}
app: {{ include "prisoner-content-hub-backend.name" . }}
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
Create external Kubernetes hostname
*/}}
{{- define "prisoner-content-hub-backend.externalHost" -}}
{{- $protocol := "http" }}
{{- if .Values.ingress.tlsEnabled }}
{{- $protocol = "https" }}
{{- end }}
{{- printf "%s://%s" $protocol .Values.ingress.hostName }}
{{- end }}
