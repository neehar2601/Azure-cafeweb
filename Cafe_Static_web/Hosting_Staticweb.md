# Hosting a Static Website on Azure Blob Storage — Café Website

> A step-by-step guide documenting the complete workflow for deploying the Café static website using Azure Blob Storage Static Website hosting. Covers storage account creation, static website enablement, file upload, and verification.

---

## Table of Contents

- [What is Azure Static Website Hosting?](#what-is-azure-static-website-hosting)
- [Architecture Overview](#architecture-overview)
- [Prerequisites](#prerequisites)
- [Step 1: Create a Resource Group](#step-1-create-a-resource-group)
- [Step 2: Create a Storage Account](#step-2-create-a-storage-account)
- [Step 3: Enable Static Website Hosting](#step-3-enable-static-website-hosting)
- [Step 4: Upload Website Files](#step-4-upload-website-files)
- [Step 5: Verify the Deployment](#step-5-verify-the-deployment)
- [Step 6: Managing the Static Website](#step-6-managing-the-static-website)
- [Troubleshooting Guide](#troubleshooting-guide)
- [Common Errors Encountered](#common-errors-encountered)
- [Useful Commands Cheatsheet](#useful-commands-cheatsheet)
- [Cost Considerations](#cost-considerations)
- [Clean Up Resources](#clean-up-resources)

---

## What is Azure Static Website Hosting?

Azure Blob Storage supports hosting static content — HTML, CSS, JavaScript, and images — directly from a storage container named `$web`. When enabled, Azure automatically provisions a public endpoint so your files are accessible over the internet without needing a web server.

**Key difference from ACI/ACR hosting:**

| Feature | ACI (Container) | Blob Storage (Static) |
|---|---|---|
| Requires Docker | ✅ Yes | ❌ No |
| Server-side logic | ✅ Yes | ❌ No |
| Cost | ~$68/month (2 vCPU) | Cents/month (pay per GB + requests) |
| Best for | Dynamic apps (PHP, Node) | Pure HTML/CSS/JS sites |
| Setup complexity | High | Low |

**This approach is ideal for the Café static website** — no PHP backend, just HTML, CSS, and images.

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────┐
│                    Your Local Machine                    │
│                                                          │
│  Cafe_Static_web/                                        │
│  ├── index.html                                          │
│  ├── css/                                                │
│  │   └── styles.css                                      │
│  └── images/                                             │
│      ├── Coffee-and-Pastries.png                         │
│      ├── Cake-Vitrine.png                                │
│      └── ...                                             │
└─────────────────────────┬───────────────────────────────┘
                          │  az storage blob upload-batch
                          ▼
┌─────────────────────────────────────────────────────────┐
│            Azure Storage Account                         │
│            (cafeweb static)                              │
│                                                          │
│  ┌───────────────────────────────────────────────────┐   │
│  │  $web container (auto-created on enable)          │   │
│  │                                                   │   │
│  │  index.html                                       │   │
│  │  css/styles.css                                   │   │
│  │  images/Coffee-and-Pastries.png                   │   │
│  │  images/Cake-Vitrine.png                          │   │
│  │  ...                                              │   │
│  └───────────────────────────────────────────────────┘   │
│                                                          │
│  Static Website Endpoint:                                │
│  https://<account>.z23.web.core.windows.net             │
└─────────────────────────────────────────────────────────┘
                          │
                          ▼
              ┌───────────────────────┐
              │    Public Internet    │
              │  Users access site    │
              │  via static endpoint  │
              └───────────────────────┘
```

---

## Prerequisites

Before starting, ensure you have:

- Azure subscription (Student, Free Trial, or Pay-As-You-Go)
- Azure CLI installed (`az --version` to check)
- Your static website files ready locally (`index.html`, `css/`, `images/`)

```bash
# Verify Azure CLI is installed
az --version

# Login to Azure
az login

# Confirm your active subscription
az account show --query "{Name:name, ID:id}" --output table
```

---

## Step 1: Create a Resource Group

```bash
# Create a resource group in an allowed region
az group create \
  --name cafe-web \
  --location southeastasia

# Verify creation
az group show --name cafe-web --query "{Name:name, Location:location}" --output table
```

> **Note:** The `southeastasia` (Singapore) region is used here as it is the region allowed by the Azure Student subscription policy. Creating resources in `eastus` or other regions will result in a `RequestDisallowedByPolicy` error.

---

## Step 2: Create a Storage Account

A Storage Account is the top-level namespace for Azure Storage. The account name must be **globally unique** across all of Azure.

```bash
# Create the storage account
az storage account create \
  --name cafestaticweb \
  --resource-group cafe-web \
  --location southeastasia \
  --sku Standard_LRS \
  --kind StorageV2

# Verify the storage account was created
az storage account show \
  --name cafestaticweb \
  --resource-group cafe-web \
  --query "{Name:name, Location:primaryLocation, Kind:kind}" \
  --output table
```

**Storage Account naming rules:**
- 3–24 characters
- Lowercase letters and numbers **only**
- Must be **globally unique** (across all Azure customers)
- No hyphens or special characters

**SKU Options:**

| SKU | Replication | Monthly Cost (Southeast Asia) | Use Case |
|-----|-------------|-------------------------------|----------|
| `Standard_LRS` | Local (3 copies in 1 datacenter) | ~$0.018/GB | Dev/test, learning |
| `Standard_GRS` | Geo-redundant (6 copies, 2 regions) | ~$0.036/GB | Production |
| `Standard_ZRS` | Zone-redundant (3 availability zones) | ~$0.023/GB | High availability |

---

## Step 3: Enable Static Website Hosting

This is the key step — it creates the special `$web` container and provisions a public static endpoint.

```bash
# Enable static website hosting
az storage blob service-properties update \
  --account-name cafestaticweb \
  --static-website \
  --index-document index.html \
  --404-document index.html

# Retrieve the public static website endpoint
az storage account show \
  --name cafestaticweb \
  --resource-group cafe-web \
  --query "primaryEndpoints.web" \
  --output tsv
```

**Expected endpoint output:**
```
https://cafestaticweb.z23.web.core.windows.net/
```

**Parameters explained:**
- `--static-website`: Enables the static hosting feature and creates the `$web` container
- `--index-document index.html`: The default document served at the root URL
- `--404-document index.html`: The page served when a file is not found (redirect to index for SPAs)

> **Verify the `$web` container was created:**
> ```bash
> az storage container list \
>   --account-name cafestaticweb \
>   --output table
> ```
> You should see `$web` in the list with `Public Access: blob`.

---

## Step 4: Upload Website Files

Upload all static files from your local `Cafe_Static_web/` directory to the `$web` container. The `upload-batch` command preserves your folder structure.

### 4.1 Get the Storage Account Key

```bash
# Get the storage account key (required for upload)
STORAGE_KEY=$(az storage account keys list \
  --account-name cafestaticweb \
  --resource-group cafe-web \
  --query "[0].value" \
  --output tsv)

# Confirm it was captured
echo "Key captured: ${STORAGE_KEY:0:10}..."
```

### 4.2 Upload All Files

```bash
# Navigate to the website directory
cd ~/Azure/Cafe_Static_web

# Upload all files to the $web container
az storage blob upload-batch \
  --account-name cafestaticweb \
  --account-key "$STORAGE_KEY" \
  --destination '$web' \
  --source . \
  --overwrite

# Verify files were uploaded
az storage blob list \
  --account-name cafestaticweb \
  --account-key "$STORAGE_KEY" \
  --container-name '$web' \
  --output table
```

**Expected output (files in `$web` container):**
```
Name                              Blob Type    Content Type
--------------------------------  -----------  --------------------------
index.html                        BlockBlob    text/html
css/styles.css                    BlockBlob    text/css
images/Cake-Vitrine.png           BlockBlob    image/png
images/Coffee-and-Pastries.png    BlockBlob    image/png
images/Coffee-Shop.png            BlockBlob    image/png
images/Cookies.png                BlockBlob    image/png
images/Cup-of-Hot-Chocolate.png   BlockBlob    image/png
images/Strawberry-Tarts.png       BlockBlob    image/png
...
```

> **Important:** Always use single quotes around `'$web'` in the shell command. Without quotes, the shell will try to expand `$web` as an environment variable and the command will fail.

---

## Step 5: Verify the Deployment

### 5.1 Get the Public URL

```bash
# Get the static website endpoint
az storage account show \
  --name cafestaticweb \
  --resource-group cafe-web \
  --query "primaryEndpoints.web" \
  --output tsv
```

### 5.2 Test with curl

```bash
# Test that the site is accessible
curl -I https://cafestaticweb.z23.web.core.windows.net/

# Expected:
# HTTP/1.1 200 OK
# Content-Type: text/html
# ...
```

### 5.3 Open in Browser

Navigate to your static endpoint URL:

```
https://cafestaticweb.z23.web.core.windows.net/
```

**What you should see:**
- ✅ The Café homepage loads with all images
- ✅ CSS styles are applied correctly
- ✅ All images (pastries, cakes, cookies) render
- ✅ `About Us` and `Contact Us` sections are visible

---

## Step 6: Managing the Static Website

### 6.1 Update a File

When you make changes to your local files, simply re-upload:

```bash
# Re-upload a single file
az storage blob upload \
  --account-name cafestaticweb \
  --account-key "$STORAGE_KEY" \
  --container-name '$web' \
  --file index.html \
  --name index.html \
  --overwrite

# Or re-upload everything
az storage blob upload-batch \
  --account-name cafestaticweb \
  --account-key "$STORAGE_KEY" \
  --destination '$web' \
  --source . \
  --overwrite
```

### 6.2 Delete a Specific File

```bash
az storage blob delete \
  --account-name cafestaticweb \
  --account-key "$STORAGE_KEY" \
  --container-name '$web' \
  --name "images/old-image.png"
```

### 6.3 List All Uploaded Files

```bash
az storage blob list \
  --account-name cafestaticweb \
  --account-key "$STORAGE_KEY" \
  --container-name '$web' \
  --query "[].{Name:name, Size:properties.contentLength, Type:properties.contentType}" \
  --output table
```

### 6.4 Disable Static Website (if needed)

```bash
az storage blob service-properties update \
  --account-name cafestaticweb \
  --static-website false
```

---

## Troubleshooting Guide

### Issue 1: `RequestDisallowedByPolicy` on Storage Account Creation

**Error:**
```
(RequestDisallowedByPolicy) Resource 'cafestaticweb' was disallowed by Azure Policy.
```

**Cause:** Tried to create the storage account in a region not allowed by the Azure Student subscription policy (e.g., `eastus`).

**Solution:**
```bash
# Check allowed regions first
az policy assignment list --output table

# Use an allowed region
az storage account create \
  --name cafestaticweb \
  --resource-group cafe-web \
  --location southeastasia \   # <-- use allowed region
  --sku Standard_LRS \
  --kind StorageV2
```

---

### Issue 2: `$web` Container Not Found / Upload Fails

**Error:**
```
The specified container does not exist.
```

**Cause:** Static website hosting was not enabled before uploading, so the `$web` container was never created.

**Solution:**
```bash
# Enable static website first
az storage blob service-properties update \
  --account-name cafestaticweb \
  --static-website \
  --index-document index.html

# Then upload
az storage blob upload-batch \
  --account-name cafestaticweb \
  --account-key "$STORAGE_KEY" \
  --destination '$web' \
  --source .
```

---

### Issue 3: Shell Expands `$web` to Empty String

**Error:**
```
ERROR: Container name '' is invalid.
```

**Cause:** Used double quotes around `$web` — the shell expands `$web` as an environment variable (which is empty).

**❌ Wrong:**
```bash
--destination "$web"    # Shell expands $web → empty string
```

**✅ Correct:**
```bash
--destination '$web'    # Single quotes prevent expansion
```

---

### Issue 4: Images or CSS Not Loading (404)

**Symptom:** The page loads but images are broken, or styles are missing.

**Cause:** Files were uploaded from the wrong directory, so the path structure doesn't match what `index.html` expects.

**Solution:**
```bash
# Must be inside Cafe_Static_web/ when you run upload-batch
cd ~/Azure/Cafe_Static_web

# Verify the structure uploaded correctly
az storage blob list \
  --account-name cafestaticweb \
  --account-key "$STORAGE_KEY" \
  --container-name '$web' \
  --output table

# You should see paths like:
# css/styles.css
# images/Coffee-and-Pastries.png
```

---

### Issue 5: Storage Account Name Already Taken

**Error:**
```
(StorageAccountAlreadyTaken) The storage account named 'cafestaticweb' is already taken.
```

**Cause:** Storage account names are globally unique across all Azure tenants.

**Solution:** Choose a unique name:
```bash
# Add a suffix to make it unique
az storage account create \
  --name cafestaticweb2601 \    # Add your initials or numbers
  --resource-group cafe-web \
  --location southeastasia \
  --sku Standard_LRS \
  --kind StorageV2
```

---

## Common Errors Encountered

| Error | Cause | Solution |
|-------|-------|----------|
| `RequestDisallowedByPolicy` | Region blocked by Azure Policy | Use `southeastasia` region |
| `StorageAccountAlreadyTaken` | Name not globally unique | Add unique suffix to name |
| `Container '$web' not found` | Static website not enabled before upload | Run `--static-website` update first |
| `Container name '' is invalid` | `$web` expanded by shell with double quotes | Use single quotes: `'$web'` |
| Images/CSS 404 on live site | Uploaded from wrong directory | `cd` into `Cafe_Static_web/` before upload |
| `AuthorizationFailure` | No `--account-key` provided | Add `--account-key "$STORAGE_KEY"` |

---

## Useful Commands Cheatsheet

### Storage Account Commands

```bash
# Create storage account
az storage account create \
  --name <name> --resource-group <rg> \
  --location <region> --sku Standard_LRS --kind StorageV2

# Enable static website
az storage blob service-properties update \
  --account-name <name> --static-website \
  --index-document index.html --404-document index.html

# Get public static website URL
az storage account show \
  --name <name> --resource-group <rg> \
  --query "primaryEndpoints.web" --output tsv

# Get storage account key
az storage account keys list \
  --account-name <name> --resource-group <rg> \
  --query "[0].value" --output tsv

# List storage accounts
az storage account list --resource-group <rg> --output table

# Delete storage account
az storage account delete --name <name> --resource-group <rg> --yes
```

### Blob / File Upload Commands

```bash
# Upload all files (batch)
az storage blob upload-batch \
  --account-name <name> --account-key "<key>" \
  --destination '$web' --source <local-dir> --overwrite

# Upload single file
az storage blob upload \
  --account-name <name> --account-key "<key>" \
  --container-name '$web' --file <file> --name <blob-name> --overwrite

# List files in $web
az storage blob list \
  --account-name <name> --account-key "<key>" \
  --container-name '$web' --output table

# Delete a file
az storage blob delete \
  --account-name <name> --account-key "<key>" \
  --container-name '$web' --name <blob-name>
```

---

## Cost Considerations

### Azure Blob Storage Pricing (Southeast Asia, as of 2026)

| Component | Cost |
|-----------|------|
| Storage (Standard LRS) | ~$0.018 / GB / month |
| Write operations | $0.065 / 10,000 operations |
| Read operations | $0.0052 / 10,000 operations |
| Data transfer OUT (first 5 GB) | Free |
| Data transfer OUT (5 GB–10 TB) | $0.087 / GB |

### Estimated Cost for the Café Static Website

```
Site size: ~10 MB (HTML + CSS + images)

Storage:              $0.018 × 0.01 GB  = ~$0.00 (rounds to zero)
Reads (1,000 visits): $0.0052 × 0.1    = ~$0.00
─────────────────────────────────────────────────
TOTAL ESTIMATE:       < $0.01 / month
```

**Compared to ACI:**
- ACI (2 vCPU): **~$68.43/month**
- Blob Static Hosting: **< $0.01/month**

> For a purely static site (no server-side logic needed), Blob Storage is dramatically cheaper.

---

## Clean Up Resources

```bash
# Delete only the storage account
az storage account delete \
  --name cafestaticweb \
  --resource-group cafe-web \
  --yes

# Delete the entire resource group (removes everything inside)
az group delete \
  --name cafe-web \
  --yes \
  --no-wait
```

> **Warning:** `az group delete` removes ALL resources in the resource group — the storage account, any containers, ACR, ACI instances, etc. Only run this if you're done with the entire project.

---
