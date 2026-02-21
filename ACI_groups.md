# Azure Container Instances (ACI) Container Groups - Complete Guide

> **Production-ready guide** based on real deployment of `cafeweb` + `cafedb`  
> Documenting complete workflow, YAML template, and all errors faced during deployment.

- **Date:** Feb 21, 2026  
- **Status:** ✅ Working deployment with data!

---

## Table of Contents

1. [What are ACI Container Groups?](#what-are-aci-container-groups)
2. [Your Working Example](#your-working-example)
3. [Complete YAML Template](#complete-yaml-template)
4. [Step-by-Step Deployment](#step-by-step-deployment)
5. [Troubleshooting Real Errors](#troubleshooting-real-errors)
6. [Common Pitfalls](#common-pitfalls)
7. [Cost Breakdown](#cost-breakdown)
8. [Best Practices](#best-practices)
9. [Next Steps](#next-steps)
10. [Quick Commands Reference](#quick-commands-reference)

---

## What are ACI Container Groups?

ACI Container Groups deploy multiple containers (up to 60) that share:

- **Shared Network:** Same public IP/FQDN and network namespace (`127.0.0.1` works between containers).
- **Lifecycle:** They start and stop together.
- **Resources:** Shared CPU/Memory pool (though allocated per container).
- **Storage:** Shared volumes (optional).

**Perfect for:** Web app + database(dev/test), frontend + backend, or app + sidecar patterns.

**vs Single Container:** A single container cannot run both a web server and a database reliably or scale them independently.

**Pricing:** Billed per group based on total resource allocation.

---

## Working Example

```
cafe-container-group
├── cafedb (MySQL)
│   ├── Image: cafeweb.azurecr.io/cafedb:latest
│   ├── Port: 3306 (internal only)
│   ├── DB: cafedb with product, order, order_item tables
│   └── Env: MYSQL_ROOT_PASSWORD, MYSQL_DATABASE=cafedb
└── cafeweb (PHP app)
    ├── Image: cafeweb.azurecr.io/cafeweb:latest
    ├── Ports: 80, 443 (public)
    ├── Connects to: 127.0.0.1:3306 (shared IP)
    └── FQDN: neeharcafe.southeastasia.azurecontainer.io
```

---

## Complete YAML Template

Copy-paste ready `cafe-container-group.yaml`:

```yaml
apiVersion: '2021-09-01'
location: southeastasia
name: cafe-container-group
properties:
  containers:
  # Database Container
  - name: cafedb
    properties:
      image: cafeweb.azurecr.io/cafedb:latest
      resources:
        requests:
          cpu: 1
          memoryInGb: 1
      ports:
      - port: 3306
        protocol: TCP
      environmentVariables:
      - name: MYSQL_ROOT_PASSWORD
        secureValue: 'your_root_password' #This will override the password efined in the image
      - name: MYSQL_DATABASE
        value: 'cafedb'
      - name: MYSQL_USER
        value: 'cafeuser'
      - name: MYSQL_PASSWORD
        secureValue: 'your_password' #This will override the password efined in the image.

  # Web App Container
  - name: cafeweb
    properties:
      image: cafeweb.azurecr.io/cafeweb:latest
      resources:
        requests:
          cpu: 2
          memoryInGb: 1
      ports:
      - port: 80
        protocol: TCP
      - port: 443
        protocol: TCP
      environmentVariables:
      - name: DB_HOST
        value: '127.0.0.1'  # Critical: shared IP for container groups
      - name: DB_PORT
        value: '3306'
      - name: DB_NAME
        value: 'cafedb'
      - name: DB_USER
        value: 'cafeuser'
      - name: DB_PASSWORD
        secureValue: 'your_password'

  imageRegistryCredentials:
  - server: cafeweb.azurecr.io
    username: cafeweb
    password: 'YOUR_ACR_PASSWORD'

  ipAddress:
    type: Public
    dnsNameLabel: neeharcafe
    ports:
    - protocol: TCP
      port: 80
    - protocol: TCP
      port: 443
  osType: Linux
  restartPolicy: Always

tags:
  environment: production
  project: cafe-web
```

---

## Step-by-Step Deployment

### Prerequisites

```bash
az login
az account set --subscription "Your-Subscription"
az provider register --namespace Microsoft.ContainerInstance
az provider register --namespace Microsoft.ContainerRegistry
```

### 1. Create Resource Group (if not already done)

```bash
az group create --name cafe-web --location southeastasia
```

### 2. Create ACR (if not exists)

```bash
az acr create --name cafeweb --resource-group cafe-web --location southeastasia --sku Standard
az acr update --name cafeweb --admin-enabled true
az acr login --name cafeweb
```

### 3. Build & Push Images

**Database Image:**

```bash
cd ~/Cafe_Dynamic_Website/mompopdb
docker build -t cafedb:latest .
docker tag cafedb:latest cafeweb.azurecr.io/cafedb:latest
docker push cafeweb.azurecr.io/cafedb:latest
```

**Web App Image:**

```bash
cd ~/Cafe_Dynamic_Website/mompopcafe
docker build -t cafeweb:latest .
docker tag cafeweb:latest cafeweb.azurecr.io/cafeweb:latest
docker push cafeweb.azurecr.io/cafeweb:latest
```

### 4. Deploy Container Group

```bash
az container create \
  --resource-group cafe-web \
  --file cafe-container-group.yaml
```

### 5. Verify Deployment

```bash
# Check status and FQDN
az container show \
  --resource-group cafe-web \
  --name cafe-container-group \
  --query "{FQDN:ipAddress.fqdn, State:instanceView.state}"

# Test DB connectivity from inside the web container
az container exec \
  --resource-group cafe-web \
  --name cafe-container-group \
  --container-name cafeweb \
  --exec-command "/bin/sh"

# Inside the shell:
mysql -h 127.0.0.1 -u cafeuser -p'CafeUserPassword123!' -e 'USE cafedb; SHOW TABLES;'
```

---

## Troubleshooting Real Errors
## Broke my app to understand the working of ACI Container Groups

### ❌ Error 1: `ERROR 2005 Unknown MySQL server host 'cafedb'`

- **Symptom:** `mysql -h cafedb -u cafeuser ...` fails.
- **Cause:** Container names are **not** resolvable by DNS inside an ACI Group.
- **Fix:** Always use `127.0.0.1` (the shared localhost for the group).
- **Action:** Updated `DB_HOST=127.0.0.1`.

### ❌ Error 2: `ERROR 2002 Can't connect to MySQL on 127.0.0.1`

- **Cause:** MySQL service failed to start or container crashed.
- **Check:** `az container logs --container-name cafedb`
- **Fix:** Ensure `MYSQL_ROOT_PASSWORD` is set and resources (1GB RAM) are sufficient.

### ❌ Error 3: Empty tables in `cafedb`

- **Cause:** `MYSQL_DATABASE` mismatch.
- **Details:** Image initialization script created `mom_pop_db`, but the app was looking for `cafedb`.
- **Fix:** Align the Dockerfile initialization script and the YAML environment variables to use the **exact same** database name.
- **Discovery:** YAML environment variables override those defined in the Dockerfile. I found this by updating the DB name in the YAML file one-by-one until the conflict was identified.

---

## Common Pitfalls

| Pitfall | Symptom | Prevention |
|---|---|---|
| Wrong DB host | Unknown host `'cafedb'` | Always use `127.0.0.1` in ACI Groups. |
| Empty DB | App shows no data | Align `MYSQL_DATABASE` name across all files. |
| MySQL not starting | Connection refused | Set `MYSQL_ROOT_PASSWORD` and check logs. |
| Env vars not read | App errors on DB connection | Use `getenv()` in PHP; verify with `env` command. |

---

## Cost Breakdown

> Estimate based on 24/7 runtime in Southeast Asia.

| Resource | vCPU | Memory | Monthly Cost (Approx) |
|---|---|---|---|
| `cafeweb` | 2 | 1 GB | ~$68.43 |
| `cafedb` | 1 | 1 GB | ~$36.03 |
| ACR (Standard) | — | — | $20.00 |
| **Total** | **3** | **2 GB** | **~$124.46** |

> **Optimization:** Use `az container stop` for dev environments to pause billing.

---

## Best Practices

- **Networking:** Use `127.0.0.1` for inter-container communication.
- **Security:** Use `secureValue` in YAML for passwords to encrypt them at rest.
- **Data Persistence:** Attach an Azure File Share volume to the `cafedb` container to prevent data loss on restart.
- **Health Checks:** Implement a `livenessProbe` to automatically restart containers if the service hangs.

---

## Next Steps

- **Persistent Storage:** Migrate database files to Azure Files.
- **HTTPS:** Map a custom domain and use an Azure Application Gateway or Caddy sidecar for SSL.
- **CI/CD:** Use GitHub Actions to build and push images to ACR on every commit.

---

## Quick Commands Reference

```bash
# Deploy/Update
az container create --resource-group cafe-web --file cafe-container-group.yaml

# View Logs
az container logs --resource-group cafe-web --name cafe-container-group --container-name cafeweb

# Execute into container
az container exec --resource-group cafe-web --name cafe-container-group --container-name cafeweb --exec-command "/bin/sh"

# Stop/Start (Save money)
az container stop --name cafe-container-group --resource-group cafe-web
az container start --name cafe-container-group --resource-group cafe-web
```

---