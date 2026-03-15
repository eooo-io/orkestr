{{/*
Expand the name of the chart.
*/}}
{{- define "orkestr.name" -}}
{{- default .Chart.Name .Values.nameOverride | trunc 63 | trimSuffix "-" }}
{{- end }}

{{/*
Create a default fully qualified app name.
We truncate at 63 chars because some Kubernetes name fields are limited to this
(by the DNS naming spec). If release name contains chart name it will be used
as a full name.
*/}}
{{- define "orkestr.fullname" -}}
{{- if .Values.fullnameOverride }}
{{- .Values.fullnameOverride | trunc 63 | trimSuffix "-" }}
{{- else }}
{{- $name := default .Chart.Name .Values.nameOverride }}
{{- if contains $name .Release.Name }}
{{- .Release.Name | trunc 63 | trimSuffix "-" }}
{{- else }}
{{- printf "%s-%s" .Release.Name $name | trunc 63 | trimSuffix "-" }}
{{- end }}
{{- end }}
{{- end }}

{{/*
Create chart name and version as used by the chart label.
*/}}
{{- define "orkestr.chart" -}}
{{- printf "%s-%s" .Chart.Name .Chart.Version | replace "+" "_" | trunc 63 | trimSuffix "-" }}
{{- end }}

{{/*
Common labels.
*/}}
{{- define "orkestr.labels" -}}
helm.sh/chart: {{ include "orkestr.chart" . }}
{{ include "orkestr.selectorLabels" . }}
{{- if .Chart.AppVersion }}
app.kubernetes.io/version: {{ .Chart.AppVersion | quote }}
{{- end }}
app.kubernetes.io/managed-by: {{ .Release.Service }}
{{- end }}

{{/*
Selector labels.
*/}}
{{- define "orkestr.selectorLabels" -}}
app.kubernetes.io/name: {{ include "orkestr.name" . }}
app.kubernetes.io/instance: {{ .Release.Name }}
{{- end }}

{{/*
Create the name of the service account to use.
*/}}
{{- define "orkestr.serviceAccountName" -}}
{{- if .Values.serviceAccount.create }}
{{- default (include "orkestr.fullname" .) .Values.serviceAccount.name }}
{{- else }}
{{- default "default" .Values.serviceAccount.name }}
{{- end }}
{{- end }}

{{/*
MariaDB fully qualified name.
*/}}
{{- define "orkestr.mariadb.fullname" -}}
{{- printf "%s-mariadb" (include "orkestr.fullname" .) | trunc 63 | trimSuffix "-" }}
{{- end }}

{{/*
MariaDB host — bundled service name or external host.
*/}}
{{- define "orkestr.mariadb.host" -}}
{{- if .Values.mariadb.enabled }}
{{- include "orkestr.mariadb.fullname" . }}
{{- else }}
{{- .Values.externalDatabase.host | default "localhost" }}
{{- end }}
{{- end }}

{{/*
MariaDB labels.
*/}}
{{- define "orkestr.mariadb.labels" -}}
{{ include "orkestr.labels" . }}
app.kubernetes.io/component: database
{{- end }}

{{/*
MariaDB selector labels.
*/}}
{{- define "orkestr.mariadb.selectorLabels" -}}
{{ include "orkestr.selectorLabels" . }}
app.kubernetes.io/component: database
{{- end }}
