# Orkestr Horizontal Scaling Guide

This document covers scaling Orkestr in Kubernetes, from a single-replica development setup to a multi-replica production deployment.

## Enabling the Horizontal Pod Autoscaler (HPA)

The chart includes a `HorizontalPodAutoscaler` resource that is disabled by default. To enable it:

```yaml
# values-production.yaml
autoscaling:
  enabled: true
  minReplicas: 2
  maxReplicas: 10
  targetCPUUtilizationPercentage: 75
  targetMemoryUtilizationPercentage: 80
```

Install or upgrade with:

```bash
helm upgrade --install orkestr ./helm/orkestr -f values-production.yaml
```

**Prerequisites:**
- The [Metrics Server](https://github.com/kubernetes-sigs/metrics-server) must be installed in your cluster for HPA to function.
- Verify with: `kubectl top pods`

## Recommended Resource Profiles

### Small (development / personal use) — 1 replica

```yaml
replicaCount: 1
resources:
  requests:
    cpu: 100m
    memory: 256Mi
  limits:
    cpu: 500m
    memory: 512Mi
mariadb:
  resources:
    requests:
      cpu: 100m
      memory: 256Mi
    limits:
      cpu: 500m
      memory: 512Mi
```

### Medium (team / staging) — 3 replicas

```yaml
replicaCount: 3
resources:
  requests:
    cpu: 250m
    memory: 512Mi
  limits:
    cpu: "1"
    memory: 1Gi
autoscaling:
  enabled: true
  minReplicas: 2
  maxReplicas: 5
  targetCPUUtilizationPercentage: 75
mariadb:
  resources:
    requests:
      cpu: 250m
      memory: 512Mi
    limits:
      cpu: "1"
      memory: 1Gi
```

### Large (production / enterprise) — 5+ replicas

```yaml
replicaCount: 5
resources:
  requests:
    cpu: 500m
    memory: 1Gi
  limits:
    cpu: "2"
    memory: 2Gi
autoscaling:
  enabled: true
  minReplicas: 3
  maxReplicas: 20
  targetCPUUtilizationPercentage: 70
  targetMemoryUtilizationPercentage: 75
```

For large deployments, consider using an external managed database (e.g., Amazon RDS, Google Cloud SQL) instead of the bundled MariaDB.

## Session Handling

Laravel defaults to file-based sessions, which do not work with multiple replicas because each pod has its own filesystem. You have two options:

### Option A: Sticky Sessions (simple)

Configure your ingress to use session affinity:

```yaml
ingress:
  enabled: true
  annotations:
    nginx.ingress.kubernetes.io/affinity: "cookie"
    nginx.ingress.kubernetes.io/session-cookie-name: "orkestr-sticky"
    nginx.ingress.kubernetes.io/session-cookie-max-age: "3600"
```

This pins each user to the same pod for the cookie's lifetime. If that pod goes down, the user's session is lost.

### Option B: Redis Session Store (recommended for production)

Deploy Redis (e.g., via the Bitnami Redis chart) and configure Orkestr to use it:

```yaml
env:
  SESSION_DRIVER: redis
  CACHE_STORE: redis

extraEnv:
  REDIS_HOST: "orkestr-redis-master"
  REDIS_PORT: "6379"
  REDIS_PASSWORD: ""
```

This gives you true stateless replicas, enabling seamless scaling and zero-downtime deployments.

## Database Connection Pooling

Each PHP process opens its own database connection. With multiple replicas, connection count grows as:

```
total_connections = replicas * workers_per_pod
```

For the built-in `php artisan serve`, each pod handles one concurrent request by default. In a production FPM setup, the default pool is typically 5-20 children.

**Recommendations:**

1. **Set connection limits in MariaDB:**
   ```sql
   SET GLOBAL max_connections = 200;
   ```

2. **Use a connection pooler** like [ProxySQL](https://proxysql.com/) between the app and database for large deployments. This multiplexes many app connections over fewer database connections.

3. **Configure persistent connections** in Laravel's `database.php` if not using a pooler:
   ```yaml
   extraEnv:
     DB_OPTIONS_PERSISTENT: "true"
   ```

## Queue Worker Scaling

The main deployment handles HTTP requests. For background jobs (webhook delivery, skill generation, etc.), deploy a separate queue worker:

```yaml
# values-queue-worker.yaml (deploy as a second release or use a subchart)
```

Or add a second Deployment manually:

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: orkestr-queue-worker
spec:
  replicas: 2
  selector:
    matchLabels:
      app.kubernetes.io/name: orkestr
      app.kubernetes.io/component: queue-worker
  template:
    spec:
      containers:
        - name: queue-worker
          image: ghcr.io/eooo-io/orkestr:latest
          command:
            - php
            - artisan
            - queue:work
            - --sleep=3
            - --tries=3
            - --max-time=3600
          envFrom:
            - configMapRef:
                name: orkestr
            - secretRef:
                name: orkestr
          resources:
            requests:
              cpu: 100m
              memory: 256Mi
            limits:
              cpu: 500m
              memory: 512Mi
```

Scale queue workers independently from HTTP pods based on queue depth.

## Ollama Sidecar Pattern for Local Models

To run local LLM inference alongside Orkestr in Kubernetes, deploy Ollama as a sidecar container in the same pod or as a separate service.

### Sidecar (same pod, GPU node required)

Add to the app deployment via `extraContainers` or by patching the deployment:

```yaml
# Patch or custom deployment overlay
spec:
  template:
    spec:
      containers:
        - name: orkestr
          # ... main container
        - name: ollama
          image: ollama/ollama:latest
          ports:
            - containerPort: 11434
          resources:
            limits:
              nvidia.com/gpu: 1
              memory: 8Gi
            requests:
              memory: 4Gi
          volumeMounts:
            - name: ollama-models
              mountPath: /root/.ollama
      volumes:
        - name: ollama-models
          persistentVolumeClaim:
            claimName: ollama-models
```

Then set:

```yaml
env:
  OLLAMA_HOST: "http://localhost:11434"
```

### Separate Service (recommended for shared use)

Deploy Ollama as its own Deployment + Service:

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: ollama
spec:
  replicas: 1
  selector:
    matchLabels:
      app: ollama
  template:
    spec:
      containers:
        - name: ollama
          image: ollama/ollama:latest
          ports:
            - containerPort: 11434
          resources:
            limits:
              nvidia.com/gpu: 1
              memory: 8Gi
---
apiVersion: v1
kind: Service
metadata:
  name: ollama
spec:
  ports:
    - port: 11434
  selector:
    app: ollama
```

Then configure Orkestr:

```yaml
env:
  OLLAMA_HOST: "http://ollama.default.svc.cluster.local:11434"
```

**GPU scheduling notes:**
- Nodes must have the [NVIDIA device plugin](https://github.com/NVIDIA/k8s-device-plugin) installed.
- Use `nodeSelector` or `tolerations` to pin Ollama pods to GPU nodes.
- Model files are large (3-70 GB). Use a PVC with sufficient storage and consider pre-pulling models via an init container.

## Monitoring

For production deployments, consider:

1. **Prometheus metrics** — expose Laravel metrics via a `/metrics` endpoint and scrape with Prometheus.
2. **Pod disruption budgets** — ensure availability during node maintenance:
   ```yaml
   apiVersion: policy/v1
   kind: PodDisruptionBudget
   metadata:
     name: orkestr
   spec:
     minAvailable: 1
     selector:
       matchLabels:
         app.kubernetes.io/name: orkestr
   ```
3. **Resource quotas** — set namespace-level limits to prevent runaway resource consumption.
