# Azure Learning Hub 🚀

Welcome to the Azure Learning Hub! This repository contains a curated collection of guides, configuration templates, and sample applications to help you master Azure services, including Virtual Machines, Containers (ACR, ACI, AKS), Blob Storage static hosting, and more.

## 📁 Repository Structure

- [**Azure_Containers/**](/Azure_Containers): Detailed guides and YAML templates for Azure Container Services.
- [**Cafe_Static_web/**](/Cafe_Static_web): Static version of the Café website, deployed via Azure Blob Storage static hosting.
- [**mompopcafe/**](/mompopcafe): A sample PHP-based web application (dynamic version).
- [**mompopdb/**](/mompopdb): Docker configuration for a MySQL database used by the sample app.
- [**azure-starter-guide.md**](/azure-starter-guide.md): A comprehensive roadmap for getting started with Azure.

---

## 🛠️ Key Components

### 1. Azure Essentials & Virtual Machines
The [**Azure Starter Guide**](/azure-starter-guide.md) covers the fundamental concepts required for any Azure deployment:
- **Azure Hierarchy**: Understanding Tenants, Subscriptions, and Resource Groups.
- **Resource Providers**: How to register services like `Microsoft.Compute` for VMs.
- **Virtual Machines**: Step-by-step commands to create and manage Ubuntu-based VMs via CLI.
- **RBAC & Policy**: Managing access control and organizational compliance.

### 2. Containers (ACR, ACI, AKS)
Containerization is a core focus of this repository. Detailed documentation can be found in the [Containers directory](/Azure_Containers):

| Service | Guide/Resource |
|---------|----------------|
| **Azure Container Registry (ACR)** | [ACR Deployment Guide](/Azure_Containers/acr-to-aci-deployment-guide.md) |
| **Azure Container Instances (ACI)** | [ACI Groups Overview](/Azure_Containers/ACI_groups.md) |
| **AKS (Kubernetes)** | Included in the [Starter Guide](/azure-starter-guide.md#step-3-resource-providers) |
| **Persistent Storage** | [ACI Volumes & File Shares](/Azure_Containers/aci-volumes-file-share.md) |
| **Networking (VNet)** | [Web + DB Network Topology](/Azure_VNet_Setup.md) |
| **Blob Storage Static Hosting** | [Static Website Hosting Guide](/Cafe_Static_web/Hosting_Staticweb.md) |

![VNet Deployment](https://devops-learner.s3.us-east-2.amazonaws.com/Azure-images/Vnet_deployment.png)

### 3. Sample Application: MomPopCafe
Throughout the guides, we use a PHP/MySQL application called **MomPopCafe** as a practical example.
- **Web App**: [PHP Source Code](/mompopcafe)
- **Database**: [MySQL Docker Configuration](/mompopdb)
- **Deployment**: Check the [Container Group YAML Templates](/Azure_Containers) for multi-container deployment examples.

### 4. Café Static Website (Blob Storage)
A lightweight static version of the Café site, served directly from Azure Blob Storage.
- **Source Files**: [Cafe_Static_web/](/Cafe_Static_web) — `index.html`, CSS, and images
- **Hosting Guide**: [Hosting_Staticweb.md](/Cafe_Static_web/Hosting_Staticweb.md) — full walkthrough from storage account creation to file upload and verification
- **Cost**: < $0.01/month (vs ~$68/month for ACI), making it ideal for purely static content.

---

## 🚀 Getting Started

### 1. Verify Your Azure CLI Setup

Before running any commands, confirm your CLI is installed and authenticated:

```bash
# Check Azure CLI version (must be installed first)
az --version

# Log in to Azure
az login

# Confirm the active subscription
az account show --query "{Name:name, SubscriptionID:id}" --output table

# List all available subscriptions (if you have more than one)
az account list --output table

# Switch to a specific subscription if needed
az account set --subscription "<your-subscription-name>"
```

> 🆕 **Not set up yet?** Follow the [Azure CLI Setup Guide](/CLI/Azure_CLI_SETUP.md) for a step-by-step installation and login walkthrough.

---

### 2. Choose Your Deployment Path

Once your CLI is ready, pick the guide that matches what you want to deploy:

| What you want to deploy | Guide to follow |
|---|---|
| Static HTML/CSS/JS site (no backend) | [Static Website Hosting Guide](/Cafe_Static_web/Hosting_Staticweb.md) |
| Containerised web app (ACI) | [ACR → ACI Deployment Guide](/Azure_Containers/acr-to-aci-deployment-guide.md) |
| Multi-container app (web + DB) | [ACI Container Groups Guide](/Azure_Containers/ACI_groups.md) |
| Persistent storage for containers | [ACI Volumes & File Shares](/Azure_Containers/aci-volumes-file-share.md) |
| Everything from scratch | [Azure Starter Guide](/azure-starter-guide.md) |

---

## 📚 Troubleshooting & Best Practices

> For common errors like `RequestDisallowedByPolicy` and `MissingSubscriptionRegistration`, refer to the **Common Errors** section in the [Azure Starter Guide](/azure-starter-guide.md#common-errors-and-solutions).

---

### 🏷️ Naming Conventions

Use a consistent pattern so resource names are self-descriptive at a glance:

```
<project>-<resourcetype>[-<env>]
```

**Rules to follow:**
- All **lowercase** — Azure is case-sensitive in many places
- Use **hyphens** (`-`) as separators for most resources
- **No hyphens** for storage accounts and ACR names (not allowed by Azure)
- Keep names **short but meaningful** — storage accounts max at 24 chars

**Real examples from this repo:**

| Resource | Name Used | Pattern |
|---|---|---|
| Resource Group | `cafe-web` | `<project>-<purpose>` |
| Storage Account | `cafestaticweb` | `<project><purpose>` (no hyphens) |
| ACR Registry | `cafeweb` | `<project><type>` (no hyphens) |
| ACI Container | `cafeweb`, `cafedb` | `<project><role>` |
| File Share | `neeharcafeweb` | `<owner><project><type>` |
| DNS Label | `neeharcafe` | `<owner><project>` |
| Container Group | `cafe-container-group` | `<project>-container-group` |

**Suggested pattern for new resources:**

```bash
# Resource Group
cafe-web                     # keep it simple for single-env projects

# Storage Account (no hyphens, globally unique)
cafestaticweb                # or add initials: nrcafestaticweb

# ACR (no hyphens)
cafeweb

# ACI / Containers
cafeweb                      # web container
cafedb                       # database container

# DNS label (globally unique across Azure)
neeharcafe                   # <yourname><project>
```

---

### 🔖 Tagging Strategy

Tags help with cost tracking, filtering, and understanding what a resource belongs to. Apply them at resource group creation and they propagate down.

```bash
# Apply tags when creating a resource group
az group create \
  --name cafe-web \
  --location southeastasia \
  --tags \
    Project=cafeweb \
    Environment=dev \
    Owner=neehar \
    Purpose=learning
```

**Recommended tags for this repo:**

| Tag Key | Example Value | Why it helps |
|---|---|---|
| `Project` | `cafeweb` | Groups all café resources |
| `Environment` | `dev` / `prod` | Distinguish dev from live |
| `Owner` | `neehar` | Who to contact about the resource |
| `Purpose` | `learning` / `static-site` / `container` | What the resource is doing |

**Tag resources individually (optional, for fine-grained tracking):**

```bash
az storage account update \
  --name cafestaticweb \
  --resource-group cafe-web \
  --tags Project=cafeweb Environment=dev Purpose=static-site Owner=neehar
```

> 💡 **Tip:** Use `az resource list --tag Project=cafeweb --output table` to instantly see all resources belonging to a project, regardless of which resource group they're in.

---

## 📄 Other Resources
This repository is continuously updated with new Azure files and guides. Explore the root directory for additional scripts and configuration templates.
