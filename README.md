# Azure Learning Hub 🚀

Welcome to the Azure Learning Hub! This repository contains a curated collection of guides, configuration templates, and sample applications to help you master Azure services, including Virtual Machines, Containers (ACR, ACI, AKS), and more.

## 📁 Repository Structure

- [**Azure_Containers/**](/Azure_Containers): Detailed guides and YAML templates for Azure Container Services.
- [**mompopcafe/**](/mompopcafe): A sample PHP-based web application.
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

### 3. Sample Application: MomPopCafe
Throughout the guides, we use a PHP/MySQL application called **MomPopCafe** as a practical example.
- **Web App**: [PHP Source Code](/mompopcafe)
- **Database**: [MySQL Docker Configuration](/mompopdb)
- **Deployment**: Check the [Container Group YAML Templates](/Azure_Containers) for multi-container deployment examples.

---

## 🚀 Getting Started

### Prerequisites
- [Azure CLI](https://learn.microsoft.com/en-us/cli/azure/install-azure-cli) installed and configured (`az login`).
- [Docker](https://docs.docker.com/get-docker/) (if building images locally).

### Quick Deployment Example (ACI)
To deploy the sample cafe application using ACI:
```bash
az container create \
  --resource-group <your-rg> \
  --file Azure_Containers/cafe-container-group.yaml
```

---

## 📚 Troubleshooting & Best Practices
- Refer to the **Common Errors** section in the [Starter Guide](/azure-starter-guide.md#common-errors-and-solutions) for issues like `RequestDisallowedByPolicy`.
- Follow the **Naming Conventions** and **Tagging Strategies** outlined in the best practices sections.

---

## 📄 Other Resources
This repository is continuously updated with new Azure files and guides. Explore the root directory for additional scripts and configuration templates.
