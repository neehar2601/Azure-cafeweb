# Azure Container Registry (ACR) to Azure Container Instances (ACI) - Complete Deployment Guide

> A step-by-step guide documenting the complete workflow from creating an Azure Container Registry, pushing Docker images, to deploying containers using Azure Container Instances (ACI).

## Table of Contents

- [Prerequisites](#prerequisites)
- [Architecture Overview](#architecture-overview)
- [Step 1: Initial Setup](#step-1-initial-setup)
- [Step 2: Create Azure Container Registry (ACR)](#step-2-create-azure-container-registry-acr)
- [Step 3: Build and Push Docker Image](#step-3-build-and-push-docker-image)
- [Step 4: Deploy to Azure Container Instances (ACI)](#step-4-deploy-to-azure-container-instances-aci)
- [Step 5: Managing Your Container](#step-5-managing-your-container)
- [Deployment Verification](#deployment-verification)
- [Troubleshooting Guide](#troubleshooting-guide)
- [Common Errors Encountered](#common-errors-encountered)
- [Useful Commands Cheatsheet](#useful-commands-cheatsheet)
- [Cost Considerations](#cost-considerations)
- [Clean Up Resources](#clean-up-resources)

---

## Prerequisites

Before starting, ensure you have:

- Azure subscription (Student, Free Trial, or Pay-As-You-Go)
- Azure CLI installed (`az --version` to check)
- Docker installed and running (`docker --version` to check)
- Basic understanding of Docker and containers
- Your application containerized with a Dockerfile

```bash
# Verify installations
az --version
docker --version

# Login to Azure
az login

# Set your default subscription
az account set --subscription "Your-Subscription-Name"
```

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────┐
│                    Your Development Machine             │
│  ┌──────────────┐                                       │
│  │  Dockerfile  │  docker build                         │
│  │  Source Code │  ─────────────────┐                   │
│  └──────────────┘                   │                   │
└─────────────────────────────────────┼───────────────────┘
                                      │
                                      ▼
                    ┌─────────────────────────────┐
                    │  Azure Container Registry   │
                    │  (cafeweb.azurecr.io)       │
                    │  ┌───────────────────────┐  │
                    │  │  cafeweb:latest       │  │
                    │  │  Docker Image         │  │
                    │  └───────────────────────┘  │
                    └──────────┬──────────────────┘
                               │ docker pull
                               ▼
                    ┌─────────────────────────────┐
                    │ Azure Container Instances   │
                    │ (neeharcafe.<region>.       │
                    │  azurecontainer.io)         │
                    │  ┌───────────────────────┐  │
                    │  │  Running Container    │  │
                    │  │  Port 80, 443         │  │
                    │  └───────────────────────┘  │
                    └─────────────────────────────┘
                               │
                               ▼
                    ┌─────────────────────────────┐
                    │      Public Internet        │
                    │  Users access your app      │
                    └─────────────────────────────┘
```

---

## Step 1: Initial Setup

### 1.1 Check Current Subscription

```bash
# View active subscription
az account show

# List all available subscriptions
az account list --output table
```

### 1.2 Check Azure Policy Restrictions

**IMPORTANT:** Before creating resources, check if your subscription has region restrictions.

```bash
# List policy assignments
az policy assignment list --output table

# Check for "Allowed resource deployment regions" policy
az policy assignment list --query "[].{Name:displayName, Scope:scope}" -o table
```

**Why this matters:** You encountered `RequestDisallowedByPolicy` error when trying to use `EastUS` region. Always verify allowed regions first!

### 1.3 Create Resource Group

```bash
# Create resource group in an allowed region
az group create \
  --name cafe-web \
  --location southeastasia

# Verify creation
az group show --name cafe-web
```

**Naming Convention Used:**
- Project: `cafe-web`
- Location: `southeastasia` (Singapore - allowed by policy)

---

## Step 2: Create Azure Container Registry (ACR)

### 2.1 Register Resource Provider

**CRITICAL STEP:** Azure requires you to register the Container Registry provider before use.

```bash
# Register the provider
az provider register --namespace Microsoft.ContainerRegistry

# Check registration status (wait until "Registered")
az provider show \
  --namespace Microsoft.ContainerRegistry \
  --query "registrationState" \
  --output tsv
```

If You get `MissingSubscriptionRegistration` error, probably this step was skipped initially. Provider registration can take 2-5 minutes.

### 2.2 Create Container Registry

```bash
# Create ACR
az acr create \
  --name cafeweb \
  --resource-group cafe-web \
  --location southeastasia \
  --sku Standard

# Verify creation
az acr show --name cafeweb --resource-group cafe-web
```

**ACR SKU Options:**
- **Basic**: $5/month, 10 GB storage, good for dev/test
- **Standard**: $20/month, 100 GB storage, recommended for production
- **Premium**: $50/month, 500 GB storage, geo-replication, private link

**Result:** Your registry is now available at `cafeweb.azurecr.io`

### 2.3 Enable Admin User (For Credential-Based Authentication)

```bash
# Enable admin user
az acr update --name cafeweb --admin-enabled true

# Get credentials
az acr credential show --name cafeweb --resource-group cafe-web
```

**Output example:**
```json
{
  "passwords": [
    {
      "name": "password",
      "value": "xxxxxxxxxxxxx"
    },
    {
      "name": "password2",
      "value": "yyyyyyyyyyyyy"
    }
  ],
  "username": "cafeweb"
}
```

**Save these credentials** - you'll need them for ACI deployment!

---

## Step 3: Build and Push Docker Image

### 3.1 Login to ACR

```bash
# Login using Azure CLI (recommended)
az acr login --name cafeweb

# Alternative: Docker login with credentials
docker login cafeweb.azurecr.io \
  --username cafeweb \
  --password <password-from-previous-step>
```

### 3.2 Build Your Docker Image

```bash
# Navigate to your project directory
cd ~/Cafe_Dynamic_Website/mompopcafe

# Build the image
docker build -t cafeweb:latest .

# Verify the image
docker images | grep cafeweb
```

### 3.3 Tag Image for ACR

```bash
# Tag with ACR registry name
docker tag cafeweb:latest cafeweb.azurecr.io/cafeweb:latest

# Verify tag
docker images | grep cafeweb.azurecr.io
```

**Tagging format:** `<registry-name>.azurecr.io/<image-name>:<tag>`

### 3.4 Push Image to ACR

```bash
# Push to ACR
docker push cafeweb.azurecr.io/cafeweb:latest

# Verify push
az acr repository list --name cafeweb --output table

# View image tags
az acr repository show-tags --name cafeweb --repository cafeweb --output table
```

**Expected output:**
```
Result
--------
cafeweb

Tag
---------
latest
```

---

## Step 4: Deploy to Azure Container Instances (ACI)

### 4.1 Register Container Instance Provider

```bash
# Register provider (if not already done)
az provider register --namespace Microsoft.ContainerInstance

# Verify registration
az provider show \
  --namespace Microsoft.ContainerInstance \
  --query "registrationState"
```

### 4.2 Get ACR Credentials

```bash
# Extract credentials into variables
ACR_USERNAME=$(az acr credential show --name cafeweb --query username --output tsv)
ACR_PASSWORD=$(az acr credential show --name cafeweb --query "passwords[0].value" --output tsv)

# Verify
echo "Username: $ACR_USERNAME"
echo "Password: $ACR_PASSWORD"
```

### 4.3 Create Container Instance

**Command:**
```bash
az container create \
  --resource-group cafe-web \
  --name cafeweb \
  --image cafeweb.azurecr.io/cafeweb:latest \
  --dns-name-label neeharcafe \
  --ports 80 443 \
  --os-type linux \
  --cpu 2 \
  --memory 1 \
  --registry-login-server cafeweb.azurecr.io \
  --registry-username $ACR_USERNAME \
  --registry-password $ACR_PASSWORD
```

### 4.4 Alternative: Using Managed Identity (More Secure)

```bash
# Get ACR resource ID
ACR_ID=$(az acr show --name cafeweb --query id --output tsv)

# Create container with system-assigned identity
az container create \
  --resource-group cafe-web \
  --name cafeweb \
  --image cafeweb.azurecr.io/cafeweb:latest \
  --dns-name-label neeharcafe \
  --ports 80 443 \
  --os-type linux \
  --cpu 2 \
  --memory 1 \
  --assign-identity --scope $ACR_ID \
  --acr-identity [system]
```

**Benefits of managed identity:**
- No credential management
- No passwords in command history
- Automatic credential rotation
- More secure

### 4.5 Verify Deployment

```bash
# Check container status
az container show \
  --name cafeweb \
  --resource-group cafe-web \
  --query "{FQDN:ipAddress.fqdn,IP:ipAddress.ip,State:instanceView.state}" \
  --output table

# Get the public URL
az container show \
  --name cafeweb \
  --resource-group cafe-web \
  --query "ipAddress.fqdn" \
  --output tsv
```

**Expected output:**
```
FQDN                                      IP              State
----------------------------------------  --------------  -------
neeharcafe.southeastasia.azurecontainer.io  20.44.226.214  Running
```

### 4.6 Access Your Application

Your container is now accessible at:
- **HTTP:** `http://neeharcafe.southeastasia.azurecontainer.io`
- **HTTPS:** `https://neeharcafe.southeastasia.azurecontainer.io` (if SSL configured in container)
**note**: This project is to understand the working of Azure, so the website is not working now.

```bash
# Test with curl
curl http://neeharcafe.southeastasia.azurecontainer.io

# Or open in browser
```

---

## Step 5: Managing Your Container

### 5.1 List Running Containers

```bash
# List all containers in resource group
az container list --resource-group cafe-web --output table

# Get detailed status
az container show --name cafeweb --resource-group cafe-web
```

### 5.2 View Container Logs

```bash
# View current logs
az container logs --name cafeweb --resource-group cafe-web

# Stream logs in real-time
az container logs --name cafeweb --resource-group cafe-web --follow
```

### 5.3 Execute Commands Inside Container

```bash
# Get interactive bash shell
az container exec \
  --resource-group cafe-web \
  --name cafeweb \
  --exec-command "/bin/bash"

# Run single command
az container exec \
  --resource-group cafe-web \
  --name cafeweb \
  --exec-command "ls -la /app"
```

### 5.4 Container Lifecycle Management

```bash
# Stop container
az container stop --name cafeweb --resource-group cafe-web

# Start container
az container start --name cafeweb --resource-group cafe-web

# Restart container
az container restart --name cafeweb --resource-group cafe-web

# Delete container
az container delete --name cafeweb --resource-group cafe-web --yes
```

### 5.5 Update Container (Redeploy)

When you update your Docker image:

```bash
# 1. Build new image
docker build -t cafeweb:v2 .

# 2. Tag for ACR
docker tag cafeweb:v2 cafeweb.azurecr.io/cafeweb:v2

# 3. Push to ACR
docker push cafeweb.azurecr.io/cafeweb:v2

# 4. Delete old container
az container delete --name cafeweb --resource-group cafe-web --yes

# 5. Create new container with updated image
az container create \
  --resource-group cafe-web \
  --name cafeweb \
  --image cafeweb.azurecr.io/cafeweb:v2 \
  --dns-name-label neeharcafe \
  --ports 80 443 \
  --os-type linux \
  --cpu 2 \
  --memory 1 \
  --registry-login-server cafeweb.azurecr.io \
  --registry-username $ACR_USERNAME \
  --registry-password $ACR_PASSWORD
```

---

## Deployment Verification

### Successful Deployment Output

After running the `az container create` command, you should see output similar to this:

![ACI Deployment Success](https://devops-learner.s3.us-east-2.amazonaws.com/Azure-images/Cafe_web_azure.png)

**Key information shown:**
- **FQDN:** `neeharcafe.southeastasia.azurecontainer.io`
- **IP Address:** `20.44.226.214`
- **State:** `Running`
- **Location:** `southeastasia`
- **Resource Group:** `cafe-web`
- **Registry Server:** `cafeweb.azurecr.io`

### Accessing Your Live Application

Once deployed, your application is accessible via the FQDN. Here's the live cafe website running on ACI:

![Cafe Website Live on ACI](https://devops-learner.s3.us-east-2.amazonaws.com/Azure-images/Cafe_web_terminal.png)

**What's working:**
- ✅ Public internet access via FQDN
- ✅ HTTP on port 80
- ✅ Website fully functional with images and styles
- ✅ Navigation menu (Home, About Us, Contact Us, Menu, Order History)
- ✅ Dynamic content rendering

**Browser shows:**
- URL: `neeharcafe.southeastasia.azurecontainer.io/argo-rollouts/`
- Title: "MSIS & CDC Café"
- All assets (images, CSS, JavaScript) loading correctly

### Verify Deployment Status

```bash
# Quick status check
az container show \
  --name cafeweb \
  --resource-group cafe-web \
  --query "{FQDN:ipAddress.fqdn,State:instanceView.state,IP:ipAddress.ip}" \
  --output table

# Expected output:
# FQDN                                        State     IP
# ------------------------------------------  --------  -------------
# neeharcafe.southeastasia.azurecontainer.io  Running   20.44.226.214
```

### Test Endpoints

```bash
# Test HTTP endpoint
curl -I http://neeharcafe.southeastasia.azurecontainer.io

# Expected: HTTP 200 OK

# Test specific routes
curl http://neeharcafe.southeastasia.azurecontainer.io/
curl http://neeharcafe.southeastasia.azurecontainer.io/argo-rollouts/
```

---

## Troubleshooting Guide

### Issue 1: RequestDisallowedByPolicy Error

**Error:**
```
(RequestDisallowedByPolicy) Resource 'cafeweb' was disallowed by Azure: 
This policy maintains a set of best available regions where your subscription 
can deploy resources.
```

**What happened:** I tried to create ACR in `EastUS`, but subscription policy only allowed `southeastasia`.

**Solution:**
```bash
# Check allowed regions
az policy assignment list --output table

# Use an allowed region
az acr create --name cafeweb --resource-group cafe-web --location southeastasia --sku Standard
```

**Lesson:** Always check Azure Policy restrictions before deploying!

---

### Issue 2: MissingSubscriptionRegistration Error

**Error:**
```
(MissingSubscriptionRegistration) The subscription is not registered 
to use namespace 'Microsoft.ContainerRegistry'
```

**What happened:** Resource provider not registered before attempting to create ACR.

**Solution:**
```bash
# Register provider
az provider register --namespace Microsoft.ContainerRegistry

# Wait for completion
az provider show \
  --namespace Microsoft.ContainerRegistry \
  --query "registrationState" \
  --output tsv
```

**Lesson:** Azure requires explicit provider registration - it's not automatic like AWS!

---

### Issue 3: Unrecognized Arguments Error
**Example**

**Error:**
```
unrecognized arguments: neeharcafe
```

**What happened:** Missing `--` before `dns-name-label` parameter in `az container create` command.

**Wrong:**
```bash
--dns-name-label neeharcafe  # Missing --
```

**Correct:**
```bash
--dns-name-label neeharcafe  # Has proper --
```

**Solution** Azure CLI is strict about parameter syntax - every flag needs `--` prefix.

---

### Issue 4: Authentication Failed When Deploying ACI

**Error:**
```
Failed to pull image from registry
```

**Cause:** Missing or incorrect ACR credentials.

**Solution:**
```bash
# Get credentials
az acr credential show --name cafeweb

# Include in container create command
az container create \
  --registry-login-server cafeweb.azurecr.io \
  --registry-username cafeweb \
  --registry-password <password>
```

**Better solution:** Use managed identity (no credentials needed).

---

### Issue 5: Container Stuck in "Creating" State

**Check logs:**
```bash
az container show --name cafeweb --resource-group cafe-web --query "instanceView"
```

**Common causes:**
- Image pull timeout
- Invalid image name/tag
- Container startup failures
- Port conflicts

**Solution:**
```bash
# View detailed logs
az container logs --name cafeweb --resource-group cafe-web

# Check events
az container show --name cafeweb --resource-group cafe-web \
  --query "instanceView.events" --output table
```

---

## Common Errors Encountered

| Error | Cause | Solution |
|-------|-------|----------|
| `RequestDisallowedByPolicy` | Region not allowed by Azure Policy | Use allowed region (southeastasia) |
| `MissingSubscriptionRegistration` | Provider not registered | `az provider register` |
| `unrecognized arguments` | Missing `--` in parameter | Add proper `--` prefix |
| `InvalidContainerImage` | Wrong image name/tag | Verify with `az acr repository list` |
| `AuthenticationFailed` | Missing ACR credentials | Add registry-username/password |
| `QuotaExceeded` | Subscription limits reached | Request quota increase |
| `DnsNameLabelNotAvailable` | DNS name already taken | Use different dns-name-label |

---

## Useful Commands Cheatsheet

### ACR Commands

```bash
# Create ACR
az acr create --name <name> --resource-group <rg> --location <region> --sku Standard

# Login
az acr login --name <name>

# List repositories
az acr repository list --name <name> --output table

# List tags
az acr repository show-tags --name <name> --repository <repo> --output table

# Delete image
az acr repository delete --name <name> --image <repo>:<tag>

# Get credentials
az acr credential show --name <name>

# Enable admin user
az acr update --name <name> --admin-enabled true
```

### ACI Commands

```bash
# Create container
az container create --resource-group <rg> --name <name> --image <image> --dns-name-label <label>

# List containers
az container list --resource-group <rg> --output table

# Show container
az container show --name <name> --resource-group <rg>

# Get logs
az container logs --name <name> --resource-group <rg>

# Exec into container
az container exec --resource-group <rg> --name <name> --exec-command "/bin/bash"

# Stop/Start/Restart
az container stop --name <name> --resource-group <rg>
az container start --name <name> --resource-group <rg>
az container restart --name <name> --resource-group <rg>

# Delete container
az container delete --name <name> --resource-group <rg> --yes
```

### Docker Commands

```bash
# Build image
docker build -t <name>:<tag> .

# Tag for ACR
docker tag <local-image> <registry>.azurecr.io/<name>:<tag>

# Push to ACR
docker push <registry>.azurecr.io/<name>:<tag>

# Pull from ACR
docker pull <registry>.azurecr.io/<name>:<tag>

# List images
docker images
```

### Resource Provider Commands

```bash
# Register provider
az provider register --namespace <namespace>

# Check status
az provider show --namespace <namespace> --query "registrationState"

# List all providers
az provider list --output table

# List registered only
az provider list --query "[?registrationState=='Registered'].namespace" --output table
```

---

## Cost Considerations

### ACR Pricing (as of 2026)

| SKU | Monthly Cost | Storage | Webhooks | Geo-replication |
|-----|--------------|---------|----------|-----------------|
| **Basic** | ~$5 | 10 GB | 2 | No |
| **Standard** | ~$20 | 100 GB | 10 | No |
| **Premium** | ~$50 | 500 GB | 500 | Yes |

**Your setup:** Standard SKU = ~$20/month

### ACI Pricing

**Your configuration:**
- 2 vCPU = $0.0000125/vCPU-second = ~$64.80/month (if running 24/7)
- 1 GB memory = $0.0000014/GB-second = ~$3.63/month
- **Total: ~$68.43/month** for continuously running container

**Cost optimization:**
- Stop container when not in use: `az container stop`
- Use Azure App Service for production (better value for always-on apps)
- Consider AKS for multiple containers (more cost-effective at scale)

### Total Monthly Cost (Your Setup)

```
ACR Standard:          $20.00
ACI (2 vCPU, 1 GB):    $68.43
─────────────────────────────
TOTAL:                 $88.43/month
```

**Free tier alternatives:**
- Azure App Service Free tier (limited, but free)
- Azure Functions Consumption plan (pay-per-execution)
- GitHub Container Registry (free for public repos)

---

## Clean Up Resources

### Delete Everything (Frees All Costs)

```bash
# Delete entire resource group (deletes ACR + ACI + all resources)
az group delete --name cafe-web --yes --no-wait

# Verify deletion
az group list --output table
```

### Delete Individual Resources

```bash
# Delete only ACI (keep ACR)
az container delete --name cafeweb --resource-group cafe-web --yes

# Delete only ACR (keep ACI)
az acr delete --name cafeweb --resource-group cafe-web --yes

# Delete specific image from ACR
az acr repository delete --name cafeweb --image cafeweb:latest --yes
```

---

## Summary: Complete Workflow

```bash
# 1. Setup
az login
az account set --subscription "Your-Subscription"
az policy assignment list --output table

# 2. Create Resource Group
az group create --name cafe-web --location southeastasia

# 3. Register Providers
az provider register --namespace Microsoft.ContainerRegistry
az provider register --namespace Microsoft.ContainerInstance

# 4. Create ACR
az acr create --name cafeweb --resource-group cafe-web --location southeastasia --sku Standard
az acr update --name cafeweb --admin-enabled true

# 5. Build & Push Image
az acr login --name cafeweb
docker build -t cafeweb:latest .
docker tag cafeweb:latest cafeweb.azurecr.io/cafeweb:latest
docker push cafeweb.azurecr.io/cafeweb:latest

# 6. Get ACR Credentials
ACR_USERNAME=$(az acr credential show --name cafeweb --query username --output tsv)
ACR_PASSWORD=$(az acr credential show --name cafeweb --query "passwords[0].value" --output tsv)

# 7. Deploy to ACI
az container create \
  --resource-group cafe-web \
  --name cafeweb \
  --image cafeweb.azurecr.io/cafeweb:latest \
  --dns-name-label neeharcafe \
  --ports 80 443 \
  --os-type linux \
  --cpu 2 \
  --memory 1 \
  --registry-login-server cafeweb.azurecr.io \
  --registry-username $ACR_USERNAME \
  --registry-password $ACR_PASSWORD

# 8. Verify & Access
az container show --name cafeweb --resource-group cafe-web \
  --query "ipAddress.fqdn" --output tsv

# Output: neeharcafe.southeastasia.azurecontainer.io
```

---

## Key Lessons Learned

1. **Azure Policy Restrictions:** Always check allowed regions before deploying
2. **Resource Provider Registration:** Required before using any Azure service
3. **Syntax Matters:** Azure CLI requires strict parameter formatting (`--parameter value`)
4. **Authentication:** ACR requires explicit credentials for ACI (or use managed identity)
5. **Resource Group = Lifecycle:** Deleting RG deletes everything inside
6. **Memory Units:** `--memory 1` = 1 GB, not `--memory 1024`
7. **DNS Labels:** Must be globally unique per region
8. **Cost Awareness:** ACI charges per second for running containers
9. **Verification is Key:** Always verify deployment with `az container show` and browser testing
10. **Public URLs:** ACI provides automatic public FQDN for easy access

---

## Next Steps

1. **Setup CI/CD:** Automate deployment using GitHub Actions or Azure DevOps
2. **Add SSL/TLS:** Configure HTTPS with Let's Encrypt or Azure-managed certificates
3. **Custom Domain:** Map your own domain to the container
4. **Monitoring:** Enable Application Insights for logging and metrics
5. **Scaling:** Consider Azure Kubernetes Service (AKS) for production workloads
6. **Security:** Use Azure Key Vault for secrets, implement network security groups
7. **Backup:** Setup automated ACR image replication
8. **Load Testing:** Test application performance under load
9. **Health Checks:** Configure liveness and readiness probes
10. **Environment Variables:** Use ACI environment variables for configuration

---

## Additional Resources

- [Azure Container Registry Documentation](https://learn.microsoft.com/en-us/azure/container-registry/)
- [Azure Container Instances Documentation](https://learn.microsoft.com/en-us/azure/container-instances/)
- [Docker Documentation](https://docs.docker.com/)
- [Azure CLI Reference](https://learn.microsoft.com/en-us/cli/azure/)
- [Azure Pricing Calculator](https://azure.microsoft.com/en-us/pricing/calculator/)

---

**Document Version:** 1.1  
**Last Updated:** February 21, 2026  
**Author:** Based on real deployment experience  
**Status:** Production-Ready Guide with Screenshots

---
