# Azure Container Instances - Persistent Storage with Azure File Share

> Complete guide to using Azure File Share volumes with Azure Container Instances (ACI) for persistent data storage, based on real MySQL deployment experience.

---

## Table of Contents

- [Why Use Volumes with ACI?](#why-use-volumes-with-aci)
- [Architecture Overview](#architecture-overview)
- [Azure File Share vs Container Storage](#azure-file-share-vs-container-storage)
- [Prerequisites](#prerequisites)
- [Step 1: Create Storage Account and File Share](#step-1-create-storage-account-and-file-share)
- [Step 2: Get Storage Account Key](#step-2-get-storage-account-key)
- [Step 3: Configure Volume in YAML](#step-3-configure-volume-in-yaml)
- [Step 4: Deploy Container with Volume](#step-4-deploy-container-with-volume)
- [Step 5: Verify Volume Mount](#step-5-verify-volume-mount)
- [Step 6: Test Data Persistence](#step-6-test-data-persistence)
- [Common Issues and Solutions](#common-issues-and-solutions)
- [MySQL-Specific Considerations](#mysql-specific-considerations)
- [Best Practices](#best-practices)
- [Cost Considerations](#cost-considerations)
- [Alternative Storage Options](#alternative-storage-options)
- [Complete Working Example](#complete-working-example)

---

## Why Use Volumes with ACI?

### Without Volumes (Default Behavior)

```
Container starts → Writes data to /var/lib/mysql → Container stops
                   ↓
              Data is LOST forever
```

**Problem:** Container storage is **ephemeral** (temporary). When container is deleted or restarted, all data is lost.

### With Azure File Share Volume

```
Container starts → Writes to /var/lib/mysql → Maps to Azure File Share
                   ↓                          ↓
         Container stops/deleted      Data persists on Azure Storage
                                              ↓
         New container starts ← Mounts same Azure File Share ← Data restored
```

**Solution:** Volume maps container directory to persistent Azure Storage. Data survives container lifecycle.

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│              Azure Container Instance                       │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  Container: mysql-test                               │   │
│  │                                                      │   │
│  │  /var/lib/mysql  ←──── Volume Mount                  │   │
│  │      ↓                                               │   │
│  │  MySQL writes data (ibdata1, *.frm, *.ibd files)     │   │
│  └──────────────────────────────────────────────────────┘   │
│                          ↓                                  │
│                   volumeMounts:                             │
│                   - name: mysql-data                        │
│                     mountPath: /var/lib/mysql               │
└─────────────────────────────────────────────────────────────┘
                            ↓
                    Persistent Connection
                            ↓
┌─────────────────────────────────────────────────────────────┐
│         Azure Storage Account: neeharcafestorage            │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  File Share: neeharcafeweb                           │   │
│  │                                                      │   │
│  │  ├── ibdata1          (MySQL system tablespace)      │   │
│  │  ├── mysql/           (MySQL system database)        │   │
│  │  ├── cafedb/          (Your application database)    │   │
│  │  │   ├── product.frm                                 │   │
│  │  │   ├── product.ibd                                 │   │
│  │  │   └── order.ibd                                   │   │
│  │  └── ib_logfile0      (MySQL transaction logs)       │   │
│  │                                                      │   │
│  │  Storage Type: Standard (LRS)                        │   │
│  │  Size: 5120 GB max                                   │   │
│  └──────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
                            ↑
                  Accessible via SMB 3.0 protocol
                  Authentication: Storage Account Key
```

---

## Azure File Share vs Container Storage

| Feature | Container Storage | Azure File Share |
|---------|-------------------|------------------|
| **Persistence** | ❌ Lost on restart | ✅ Survives restarts |
| **Data lifecycle** | Tied to container | Independent of container |
| **Sharing** | ❌ Single container only | ✅ Multiple containers can mount |
| **Backup** | ❌ Must export manually | ✅ Azure Backup supported |
| **Performance** | Fast (local disk) | Moderate (network storage) |
| **Use case** | Temporary/cache data | Databases, user uploads, logs |
| **Cost** | Included in ACI | Separate storage cost |

---

## Prerequisites

```bash
# Verify Azure CLI
az --version

# Login
az login

# Set subscription
az account set --subscription "Your-Subscription-Name"

# Verify resource group exists
az group show --name cafe-web
```

---

## Step 1: Create Storage Account and File Share

### 1.1 Create Storage Account

```bash
# Create storage account
az storage account create \
  --name neeharcafestorage \
  --resource-group cafe-web \
  --location southeastasia \
  --sku Standard_LRS \
  --kind StorageV2

# Verify creation
az storage account show \
  --name neeharcafestorage \
  --resource-group cafe-web \
  --query "name" --output tsv
```

**Naming rules:**
- Must be globally unique
- 3-24 characters
- Lowercase letters and numbers only
- No hyphens or special characters

**SKU options:**
- `Standard_LRS`: Locally redundant (~$0.02/GB/month)
- `Standard_GRS`: Geo-redundant (~$0.04/GB/month)
- `Premium_LRS`: High performance (~$0.15/GB/month)

### 1.2 Create File Share

```bash
# Get storage account key first (needed for share creation)
STORAGE_KEY=$(az storage account keys list \
  --account-name neeharcafestorage \
  --resource-group cafe-web \
  --query "[0].value" --output tsv)

# Create file share
az storage share create \
  --name neeharcafeweb \
  --account-name neeharcafestorage \
  --account-key "$STORAGE_KEY" \
  --quota 10

# Verify creation
az storage share show \
  --name neeharcafeweb \
  --account-name neeharcafestorage \
  --account-key "$STORAGE_KEY"
```

**Parameters:**
- `--quota`: Maximum size in GB (default: 5120 GB)
- `--name`: File share name (must be lowercase, 3-63 chars)

---

## Step 2: Get Storage Account Key

**Storage account key is required for volume authentication in ACI.**

```bash
# Get primary key
az storage account keys list \
  --account-name neeharcafestorage \
  --resource-group cafe-web \
  --query "[0].value" --output tsv

# Get both keys (for rotation)
az storage account keys list \
  --account-name neeharcafestorage \
  --resource-group cafe-web \
  --output table
```

**Example output:**
```
KeyName    Permissions    Value
---------  -------------  --------------------------------------------------
key1       Full           <your-storage-account-key>
key2       Full           Ab+CdEfGh12IjKlMnOpQrStUvWxYz34567890ABCDE...
```

**Security note:** Treat storage keys like passwords. Never commit to Git.

---

## Step 3: Configure Volume in YAML

### Understanding YAML Volume Structure

```yaml
properties:
  # 1. DEFINE volumes at container group level
  volumes:
  - name: mysql-data                    # Logical name (reference in mount)
    azureFile:
      shareName: neeharcafeweb          # File share name from Step 1
      storageAccountName: neeharcafestorage  # Storage account from Step 1
      storageAccountKey: <Enter the storage account key>    # Key from Step 2

  containers:
  - name: mysql-test
    properties:
      # 2. MOUNT volume inside container
      volumeMounts:
      - name: mysql-data                # Must match volume name above
        mountPath: /var/lib/mysql       # Directory inside container
```

**Key concepts:**
1. **Volume definition:** Declares connection to Azure File Share (what to mount)
2. **Volume mount:** Maps volume to container path (where to mount)
3. **Name matching:** `volumeMounts.name` MUST equal `volumes.name`

### Complete Minimal YAML

```yaml
apiVersion: '2021-09-01'
location: southeastasia
name: test-mysql-volume
properties:
  volumes:
  - name: mysql-data
    azureFile:
      shareName: neeharcafeweb
      storageAccountName: neeharcafestorage
      storageAccountKey: <Enter the storage account key>

  containers:
  - name: mysql-test
    properties:
      image: mysql:8.0
      resources:
        requests:
          cpu: 0.5
          memoryInGb: 0.5
      environmentVariables:
      - name: MYSQL_ROOT_PASSWORD
        value: 'test123'
      - name: MYSQL_DATABASE
        value: 'testdb'
      volumeMounts:
      - name: mysql-data
        mountPath: /var/lib/mysql
      ports:
      - port: 3306
        protocol: TCP

  osType: Linux
  restartPolicy: Always
```

**Save as:** `mysql-volume-test.yaml`

---

## Step 4: Deploy Container with Volume

```bash
# Deploy using YAML file
az container create \
  --resource-group cafe-web \
  --file mysql-volume-test.yaml

# Monitor deployment (wait for "Running" state)
az container show \
  --resource-group cafe-web \
  --name test-mysql-volume \
  --query "containers[0].instanceView.currentState.state" --output tsv
```

**Deployment timeline:**
- Creating: 10-30 seconds (pulling image)
- Running: MySQL initialization (30-60 seconds first time)
- Ready: MySQL accepts connections

---

## Step 5: Verify Volume Mount

### 5.1 Check Container Status

```bash
# Get detailed container status
az container show \
  --resource-group cafe-web \
  --name test-mysql-volume \
  --query "{State:containers[0].instanceView.currentState.state, Volumes:properties.volumes[0].azureFile}" \
  --output json
```

**Expected output:**
```json
{
  "State": "Running",
  "Volumes": {
    "shareName": "neeharcafeweb",
    "storageAccountName": "neeharcafestorage"
  }
}
```

### 5.2 Verify Mount Inside Container

```bash
# Get shell access
az container exec \
  --resource-group cafe-web \
  --name test-mysql-volume \
  --container-name mysql-test \
  --exec-command "/bin/bash"

# Inside container, run these commands:
df -h /var/lib/mysql
mount | grep mysql
ls -la /var/lib/mysql
```

**Expected output:**
```
# df -h /var/lib/mysql
Filesystem                                Size  Used Avail Use% Mounted on
//neeharcafestorage.file.core.windows... 5.0T  100M  5.0T   1% /var/lib/mysql

# mount | grep mysql
//neeharcafestorage.file.core.windows.net/neeharcafeweb on /var/lib/mysql type cifs (rw,relatime)

# ls -la /var/lib/mysql
total 188420
drwxrwxrwx 6 mysql mysql        0 Feb 24 13:15 .
drwxr-xr-x 1 root  root      4096 Feb 24 13:14 ..
-rw-r----- 1 mysql mysql       56 Feb 24 13:15 auto.cnf
-rw-r----- 1 mysql mysql 12582912 Feb 24 13:15 ibdata1
-rw-r----- 1 mysql mysql 50331648 Feb 24 13:15 ib_logfile0
drwxr-x--- 2 mysql mysql        0 Feb 24 13:15 mysql
drwxr-x--- 2 mysql mysql        0 Feb 24 13:15 testdb
```

✅ **Mount successful if you see:**
- `cifs` filesystem type (Azure Files uses SMB/CIFS)
- Storage account name in mount path
- MySQL data files (ibdata1, ib_logfile0, mysql/, testdb/)

---

## Step 6: Test Data Persistence

### 6.1 Create Test Data

```bash
# Connect to MySQL (from inside container or another container)
mysql -h 127.0.0.1 -u root -p'test123' -e "\
  USE testdb; \
  CREATE TABLE persist_test(id INT PRIMARY KEY, note VARCHAR(100)); \
  INSERT INTO persist_test VALUES (1, 'Data before container restart'); \
  SELECT * FROM persist_test;"
```

**Expected output:**
```
+----+----------------------------------+
| id | note                             |
+----+----------------------------------+
|  1 | Data before container restart    |
+----+----------------------------------+
```

### 6.2 Verify Data on Azure File Share

```bash
# Exit container
exit

# List files in Azure File Share
az storage file list \
  --share-name neeharcafeweb \
  --account-name neeharcafestorage \
  --account-key "$STORAGE_KEY" \
  --output table

# Check specific database directory
az storage file list \
  --share-name neeharcafeweb \
  --path testdb \
  --account-name neeharcafestorage \
  --account-key "$STORAGE_KEY" \
  --output table
```

**You should see MySQL files:**
- `persist_test.frm` (table structure)
- `persist_test.ibd` (table data)

### 6.3 Delete and Recreate Container

```bash
# Delete container (this normally destroys data)
az container delete \
  --resource-group cafe-web \
  --name test-mysql-volume \
  --yes

# Verify deletion
az container list --resource-group cafe-web --output table

# Recreate container with SAME volume
az container create \
  --resource-group cafe-web \
  --file mysql-volume-test.yaml

# Wait for Running state
az container show \
  --resource-group cafe-web \
  --name test-mysql-volume \
  --query "containers[0].instanceView.currentState.state" --output tsv
```

### 6.4 Verify Data Survived

```bash
# Connect to new container
az container exec \
  --resource-group cafe-web \
  --name test-mysql-volume \
  --container-name mysql-test \
  --exec-command "/bin/bash"

# Query the data
mysql -h 127.0.0.1 -u root -p'test123' -e "USE testdb; SELECT * FROM persist_test;"
```

**✅ SUCCESS if you see:**
```
+----+----------------------------------+
| id | note                             |
+----+----------------------------------+
|  1 | Data before container restart    |
+----+----------------------------------+
```

**🎉 Data persisted! The table and row survived container deletion.**

---

## Common Issues and Solutions

### Issue 1: Container Crashes Immediately (No Logs)

**Symptom:**
```bash
az container show --query "containers[0].instanceView.currentState.state"
# Output: "Terminated" or "Failed"

az container logs --name test-mysql-volume
# Output: (empty or minimal)
```

**Causes:**
1. MySQL can't write to `/var/lib/mysql` (permission issue)
2. Azure File Share already has incompatible data
3. Volume mount failed silently

**Solutions:**

**A. Clean the Azure File Share**
```bash
# Delete all files in share
az storage file delete-batch \
  --source neeharcafeweb \
  --account-name neeharcafestorage \
  --account-key "$STORAGE_KEY"

# Redeploy
az container delete --name test-mysql-volume --resource-group cafe-web --yes
az container create --resource-group cafe-web --file mysql-volume-test.yaml
```

**B. Test Without Volume First**
```yaml
# Remove volumeMounts section temporarily
containers:
- name: mysql-test
  properties:
    # ... all other settings
    # volumeMounts: []  # Comment out or remove
```

If container runs without volume, the issue is volume-related.

**C. Use emptyDir Volume (Temporary Alternative)**
```yaml
volumes:
- name: mysql-data
  emptyDir: {}  # In-memory, lost on restart
```

This tests if MySQL itself works, isolating volume issues.

---

### Issue 2: YAML Syntax Error - `mount path`

**Error:**
```
(InvalidRequestContent) Could not find member 'mount path' on object of type 'VolumeMount'
```

**Cause:** Wrong YAML property name.

**❌ WRONG:**
```yaml
volumeMounts:
- mount path: /var/lib/mysql  # Space in property name
  name: mysql-data
```

**✅ CORRECT:**
```yaml
volumeMounts:
- mountPath: /var/lib/mysql   # camelCase, no space
  name: mysql-data
```

---

### Issue 3: Data Not Persisting After Restart

**Symptom:** Data is lost after container restart, even with volume.

**Check:**
```bash
# 1. Verify volume is defined
az container show --name test-mysql-volume --query "properties.volumes"

# 2. Verify mount point
az container show --name test-mysql-volume \
  --query "properties.containers[0].properties.volumeMounts"

# 3. Check if same share is used
az container show --name test-mysql-volume \
  --query "properties.volumes[0].azureFile.shareName"
```

**Causes:**
- Volume not mounted (missing `volumeMounts` section)
- Wrong `mountPath` (MySQL writes elsewhere)
- Different share used in new deployment
- Share was deleted/recreated

**Solution:** Ensure YAML has matching volume definition and mount.

---

### Issue 4: Permission Denied Errors in MySQL Logs

**Error (in `az container logs`):**
```
chown: changing ownership of '/var/lib/mysql': Operation not permitted
mysqld: Can't create/write to file '/var/lib/mysql/ibdata1' (Errcode: 13 - Permission denied)
```

**Cause:** Azure File Share uses CIFS/SMB, which has limited permission control. MySQL expects to `chown` files to `mysql:mysql` user.

**Workaround 1: Use Official MySQL Image (Already Does This)**
The official `mysql:8.0` image handles Azure Files better than custom images.

**Workaround 2: Custom Dockerfile with Entrypoint**
```dockerfile
FROM mysql:8.0
COPY custom-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/custom-entrypoint.sh
ENTRYPOINT ["custom-entrypoint.sh"]
```

**custom-entrypoint.sh:**
```bash
#!/bin/bash
# Skip chown on Azure Files (fails anyway)
export MYSQL_INITDB_SKIP_TZINFO=1
exec docker-entrypoint.sh mysqld
```

**Workaround 3: Mount Subdirectory**
```yaml
volumeMounts:
- mountPath: /var/lib/mysql/data  # Mount to subdir, not root
  name: mysql-data
```

Then configure MySQL to use `/var/lib/mysql/data` as datadir.

---

### Issue 5: Slow MySQL Performance with Azure Files

**Symptom:** Queries are slow, high latency.

**Cause:** Azure File Share uses network storage (SMB over internet). Not as fast as local disk.

**Solutions:**

**1. Upgrade Storage SKU**
```bash
az storage account update \
  --name neeharcafestorage \
  --resource-group cafe-web \
  --sku Premium_LRS  # Faster, but more expensive
```

**2. Use Azure Disk (Not Supported in ACI)**
ACI doesn't support Azure Disk volumes. Consider:
- Azure Container Apps (supports more volume types)
- Azure Kubernetes Service (AKS) with persistent volumes

**3. Optimize MySQL Configuration**
Add to container environment variables:
```yaml
environmentVariables:
- name: MYSQL_INITDB_SKIP_TZINFO
  value: '1'
```

**4. Use Azure Database for MySQL (Managed Service)**
For production, consider managed service instead of DIY MySQL in ACI.

---

## MySQL-Specific Considerations

### Why MySQL + Azure Files is Challenging

| MySQL Requirement | Azure Files Limitation |
|-------------------|------------------------|
| Needs file ownership (mysql:mysql) | CIFS doesn't support Linux ownership |
| Requires specific permissions (660, 700) | CIFS uses 777 by default |
| Writes temp files rapidly | Network latency impacts performance |
| Expects fsync support | SMB has different sync semantics |

**Result:** MySQL + Azure Files works but has limitations.

### Recommended Alternatives for Production

1. **Azure Database for MySQL** (Managed PaaS)
   - Fully managed
   - Automatic backups
   - High availability
   - No volume management needed

2. **Azure Container Apps + Azure Disk**
   - Better volume support
   - Block storage (faster)
   - Similar to ACI but more features

3. **Azure Kubernetes Service (AKS)**
   - Full persistent volume support
   - Azure Disk, Azure Files, NFS
   - Production-grade orchestration

### When to Use ACI + Azure Files

✅ **Good for:**
- Development/testing
- Low-traffic applications
- Non-critical data
- Learning/experimentation

❌ **Not recommended for:**
- High-performance databases
- Production critical data
- High-concurrency workloads
- Financial/healthcare data

---

## Best Practices

### 1. Storage Account Configuration

```bash
# Enable soft delete (recover deleted files)
az storage blob service-properties delete-policy update \
  --account-name neeharcafestorage \
  --days-retained 7 \
  --enable true

# Enable backup
az backup vault create --resource-group cafe-web --name cafe-backup-vault
```

### 2. Security

**❌ DON'T:**
```yaml
# Don't put storage key in YAML directly (it's in Git!)
storageAccountKey: <Enter the storage account key>
```

**✅ DO:**
```bash
# Use Azure Key Vault
az keyvault create --name cafewebvault --resource-group cafe-web
az keyvault secret set --vault-name cafewebvault --name storage-key --value "$STORAGE_KEY"

# Reference in YAML (ACI supports this)
azureFile:
  shareName: neeharcafeweb
  storageAccountName: neeharcafestorage
  storageAccountKey:
    secretReference: storage-key
    vaultResourceId: /subscriptions/.../cafewebvault
```

### 3. Monitoring

```bash
# Enable storage metrics
az monitor metrics list \
  --resource /subscriptions/.../neeharcafestorage \
  --metric-names Availability Ingress Egress

# Set up alerts for storage failures
az monitor metrics alert create \
  --name storage-availability-alert \
  --resource-group cafe-web \
  --scopes /subscriptions/.../neeharcafestorage \
  --condition "avg Availability < 99"
```

### 4. Backup Strategy

```bash
# Manual backup (copy share contents)
az storage file copy start-batch \
  --source-account-name neeharcafestorage \
  --source-share neeharcafeweb \
  --destination-account-name neeharcafebackup \
  --destination-share backup-$(date +%Y%m%d)

# Automated with Azure Backup (recommended)
az backup protection enable-for-azurefileshare \
  --vault-name cafe-backup-vault \
  --resource-group cafe-web \
  --storage-account neeharcafestorage \
  --azure-file-share neeharcafeweb \
  --policy-name DefaultPolicy
```

---

## Cost Considerations

### Azure Files Pricing (Southeast Asia region, as of 2026)

| Tier | Storage ($/GB/month) | Transactions | Snapshot |
|------|---------------------|--------------|----------|
| **Standard (LRS)** | $0.018 | $0.065/10K ops | $0.018/GB |
| **Standard (GRS)** | $0.036 | $0.065/10K ops | $0.036/GB |
| **Premium** | $0.150 | Included | $0.150/GB |

### Example Cost for MySQL Setup (10 GB data)

```
Storage Account (Standard LRS):
- 10 GB data:              $0.18/month
- Transactions (estimate): $0.20/month
- Snapshots (optional):    $0/month (if not used)
─────────────────────────────────────
Subtotal:                  $0.38/month

ACI Container:
- 0.5 vCPU (24/7):        $16.20/month
- 0.5 GB RAM (24/7):       $1.82/month
─────────────────────────────────────
Subtotal:                 $18.02/month

TOTAL:                    $18.40/month
```

**Cost optimization:**
- Stop container when not in use (storage cost continues)
- Use LRS instead of GRS if don't need geo-redundancy
- Clean up old snapshots
- Monitor usage with Azure Cost Management

---

## Alternative Storage Options

### 1. emptyDir (In-Memory, Temporary)

```yaml
volumes:
- name: temp-storage
  emptyDir: {}
```

**Pros:**
- Fast (RAM-based)
- No extra cost
- No permission issues

**Cons:**
- Lost on container restart
- Limited by container memory
- Not suitable for databases

**Use case:** Temporary cache, build artifacts

---

### 2. Azure Blob Storage (Not Directly Supported)

ACI doesn't support mounting Blob Storage as volume. Workaround:

```bash
# Use BlobFuse in container (mount Blob as filesystem)
# Requires privileged container (security risk)
```

**Not recommended for ACI.**

---

### 3. Azure Disk (Not Supported in ACI)

Block storage, better performance than Azure Files, but:

❌ ACI doesn't support Azure Disk volumes  
✅ Use AKS or Container Apps instead

---

### 4. NFS (Azure Files Premium)

```bash
# Create NFS-enabled file share (Premium tier only)
az storage account create \
  --name cafenfsstorage \
  --resource-group cafe-web \
  --sku Premium_LRS \
  --enable-nfs-v3 true

az storage share-rm create \
  --storage-account cafenfsstorage \
  --name nfsshare \
  --enabled-protocol NFS
```

**YAML:**
```yaml
volumes:
- name: nfs-volume
  azureFile:
    shareName: nfsshare
    storageAccountName: cafenfsstorage
    mountOptions: "vers=3.0"
```

**Pros:**
- Better performance than SMB
- Native Linux permissions

**Cons:**
- Premium tier only (expensive)
- Requires virtual network configuration

---

## Complete Working Example

### Full Production-Ready YAML

```yaml
apiVersion: '2021-09-01'
location: southeastasia
name: cafe-container-group
properties:
  volumes:
  - name: persistent-storage
    azureFile:
      shareName: <Enter the share name>
      storageAccountName: <Enter the storage account name>
      storageAccountKey: <Enter the storage account key>

  containers:
  - name: cafedb
    properties:
      image: mysql:8.0
      resources:
        requests:
          cpu: 1
          memoryInGb: 1
      environmentVariables:
      - name: MYSQL_ROOT_PASSWORD
        secureValue: '<Enter the MySQL root password>'
      - name: MYSQL_DATABASE
        value: 'cafedb'
      - name: MYSQL_USER
        value: 'cafeuser'
      - name: MYSQL_PASSWORD
        secureValue: '<Enter the MySQL user password>'
      volumeMounts:
      - mountPath: /var/lib/mysql
        name: persistent-storage
      ports:
      - port: 3306
        protocol: TCP

  - name: cafeweb
    properties:
      image: cafeweb.azurecr.io/cafeweb:latest
      resources:
        requests:
          cpu: 2
          memoryInGb: 1
      environmentVariables:
      - name: DB_HOST
        value: '127.0.0.1'
      - name: DB_PORT
        value: '3306'
      - name: DB_NAME
        value: 'cafedb'
      - name: DB_USER
        value: 'cafeuser'
      - name: DB_PASSWORD
        secureValue: '<Enter the MySQL user password>'
      ports:
      - port: 80
        protocol: TCP
      - port: 443
        protocol: TCP
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

### Deployment Commands

```bash
# 1. Create storage resources
STORAGE_KEY=$(az storage account keys list \
  --account-name neeharcafestorage \
  --resource-group cafe-web \
  --query "[0].value" --output tsv)

az storage share create \
  --name neeharcafeweb \
  --account-name neeharcafestorage \
  --account-key "$STORAGE_KEY" \
  --quota 10

# 2. Deploy container group
az container create \
  --resource-group cafe-web \
  --file cafe-container-group-volume.yaml

# 3. Verify deployment
az container show \
  --resource-group cafe-web \
  --name cafe-container-group \
  --query "{FQDN:ipAddress.fqdn,State:instanceView.state,Volume:properties.volumes[0].azureFile.shareName}"

# 4. Test persistence
az container exec \
  --resource-group cafe-web \
  --name cafe-container-group \
  --container-name cafeweb \
  --exec-command "/bin/sh"

# Inside container:
mysql -h 127.0.0.1 -u cafeuser -p'<Enter the MySQL user password>' \
  -e "CREATE DATABASE IF NOT EXISTS testpersist; USE testpersist; CREATE TABLE test(id INT); INSERT INTO test VALUES (1); SELECT * FROM test;"

# 5. Delete and recreate
az container delete --resource-group cafe-web --name cafe-container-group --yes
az container create --resource-group cafe-web --file cafe-container-group-volume.yaml

# 6. Verify data survived
az container exec \
  --resource-group cafe-web \
  --name cafe-container-group \
  --container-name cafeweb \
  --exec-command "/bin/sh"

mysql -h 127.0.0.1 -u cafeuser -p'<Enter the MySQL user password>' \
  -e "USE testpersist; SELECT * FROM test;"
```

---

## Cleanup

```bash
# Delete container group (volume remains)
az container delete --resource-group cafe-web --name cafe-container-group --yes

# Delete file share (destroys data)
az storage share delete \
  --name neeharcafeweb \
  --account-name neeharcafestorage \
  --account-key "$STORAGE_KEY"

# Delete storage account (destroys all shares)
az storage account delete \
  --name neeharcafestorage \
  --resource-group cafe-web \
  --yes

# Delete resource group (destroys everything)
az group delete --name cafe-web --yes --no-wait
```

---

## Summary

### Key Takeaways

1. **Azure Files = Persistent Storage:** Survives container restarts and deletions
2. **Volume Definition:** Declared at container group level, mounted per container
3. **YAML Syntax:** Use `mountPath` (camelCase), not `mount path`
4. **MySQL Challenges:** Permission issues common due to CIFS/SMB limitations
5. **Testing:** Always test persistence by deleting/recreating container
6. **Production:** Consider Azure Database for MySQL or AKS for critical workloads
7. **Security:** Use Key Vault for storage keys, not direct YAML
8. **Cost:** Storage is separate from ACI compute costs

### Workflow Recap

```
1. Create Storage Account → 2. Create File Share → 3. Get Storage Key
                                                           ↓
                                              4. Add to YAML (volumes + volumeMounts)
                                                           ↓
                                              5. Deploy ACI with az container create
                                                           ↓
                                              6. Verify mount with df -h
                                                           ↓
                                              7. Test persistence (delete + recreate)
```

---

**Document Version:** 1.0  
**Last Updated:** February 24, 2026  
**Status:** Production-Ready Guide Based on Real Testing

**You now understand how to use Azure File Share volumes with ACI for persistent storage! 🎉**
