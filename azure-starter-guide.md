# Azure Starter Guide: From Subscription to Deployment

> A comprehensive guide for developers coming from AWS or starting fresh with Azure. This document covers the fundamental concepts and step-by-step commands to get your first resources deployed.

## Table of Contents

- [Understanding Azure's Hierarchy](#understanding-azures-hierarchy)
- [Step 1: Subscription Setup](#step-1-subscription-setup)
- [Step 2: Resource Groups](#step-2-resource-groups)
- [Step 3: Resource Providers](#step-3-resource-providers)
- [Step 4: Azure Policy](#step-4-azure-policy)
- [Step 5: RBAC (Role-Based Access Control)](#step-5-rbac-role-based-access-control)
- [Step 6: Deploying Resources](#step-6-deploying-resources)
- [Common Errors and Solutions](#common-errors-and-solutions)
- [Azure vs AWS Quick Reference](#azure-vs-aws-quick-reference)
- [Best Practices](#best-practices)

---

## Understanding Azure's Hierarchy

Azure organizes resources in a hierarchical structure:

```
Management Group (Optional)
    └── Subscription (Billing boundary)
        └── Resource Group (Lifecycle container)
            └── Resources (VMs, Storage, Databases, etc.)
```

### Key Concepts

| Component | Purpose | AWS Equivalent |
|-----------|---------|----------------|
| **Tenant** | Azure AD organization | AWS Organization Root |
| **Management Group** | Organize multiple subscriptions | Organizational Units (OUs) |
| **Subscription** | Billing boundary, quota limits | AWS Account |
| **Resource Group** | Logical container for related resources | Tags + CloudFormation Stack (loosely) |
| **Resource** | Individual service instance | AWS Resource |

---

## Step 1: Subscription Setup

A **subscription** is the fundamental billing and access control boundary in Azure.

### Check Your Current Subscription

```bash
# View current subscription
az account show

# List all subscriptions you have access to
az account list --output table

# Set a specific subscription as active
az account set --subscription "Your-Subscription-Name-or-ID"
```

### Subscription Types

- **Free Trial**: $200 credit for 30 days + 12 months of free services
- **Pay-As-You-Go**: Standard billing
- **Student**: $100 credit, no credit card required
- **Enterprise Agreement**: Organization-wide contract

### Important Notes

- Each subscription has its own quota limits (VMs, cores, storage, etc.)
- RBAC permissions can be assigned at subscription level
- Azure Policies can be applied at subscription scope
- Subscriptions can be moved between management groups

---

## Step 2: Resource Groups

A **Resource Group** is a logical container that holds related Azure resources. It's a lifecycle boundary—when you delete the resource group, all resources inside are deleted.

### Creating a Resource Group

```bash
# Create a resource group
az group create \
  --name my-resource-group \
  --location southeastasia

# List all resource groups
az group list --output table

# Show details of a specific resource group
az group show --name my-resource-group
```

### Available Locations

```bash
# List all available Azure regions
az account list-locations --output table

# Common regions for India
# - southindia (Chennai)
# - centralindia (Pune)
# - westindia (Mumbai)
# - southeastasia (Singapore)
# - eastasia (Hong Kong)
```

### Resource Group Best Practices

1. **Group by lifecycle**: Resources that share the same lifecycle should be in the same RG
2. **Group by environment**: `myapp-dev-rg`, `myapp-prod-rg`
3. **Group by application**: `webapp-frontend-rg`, `webapp-backend-rg`
4. **Use consistent naming**: `<project>-<env>-<region>-rg`

### Example Naming Convention

```bash
# Development environment
az group create --name myapp-dev-southeastasia-rg --location southeastasia

# Production environment
az group create --name myapp-prod-southeastasia-rg --location southeastasia

# Shared services
az group create --name myapp-shared-southeastasia-rg --location southeastasia
```

---

## Step 3: Resource Providers

**Resource Providers** are Azure services exposed through namespaces like `Microsoft.Compute`, `Microsoft.Storage`, `Microsoft.ContainerRegistry`. You must register a provider in your subscription before you can create resources of that type.

### Why Registration is Required

- **Security**: Prevents unauthorized use of services
- **Governance**: Organization can control which services are available
- **Least Privilege**: Only enable services you intend to use

### Check Provider Status

```bash
# List all resource providers and their registration state
az provider list --output table

# Check status of a specific provider
az provider show --namespace Microsoft.ContainerRegistry --query "registrationState"

# List only registered providers
az provider list --query "[?registrationState=='Registered'].namespace" --output table
```

### Register a Provider

```bash
# Register a provider (this can take a few minutes)
az provider register --namespace Microsoft.ContainerRegistry

# Common providers you'll need:
az provider register --namespace Microsoft.Compute           # Virtual Machines
az provider register --namespace Microsoft.Storage           # Storage Accounts
az provider register --namespace Microsoft.Network           # Virtual Networks
az provider register --namespace Microsoft.ContainerRegistry # Container Registry
az provider register --namespace Microsoft.ContainerService  # AKS (Kubernetes)
az provider register --namespace Microsoft.Web              # App Service
az provider register --namespace Microsoft.Sql              # SQL Database
```

### Wait for Registration

```bash
# Check if registration is complete
az provider show \
  --namespace Microsoft.ContainerRegistry \
  --query "registrationState" \
  --output tsv

# Registration states:
# - NotRegistered: Not yet registered
# - Registering: In progress
# - Registered: Ready to use
```

### Common Resource Providers

| Service | Namespace | Use Case |
|---------|-----------|----------|
| Virtual Machines | `Microsoft.Compute` | IaaS compute |
| Storage | `Microsoft.Storage` | Blobs, files, queues |
| Virtual Network | `Microsoft.Network` | Networking resources |
| Container Registry | `Microsoft.ContainerRegistry` | Docker registry |
| AKS | `Microsoft.ContainerService` | Managed Kubernetes |
| App Service | `Microsoft.Web` | PaaS web hosting |
| Azure SQL | `Microsoft.Sql` | Managed SQL databases |
| Cosmos DB | `Microsoft.DocumentDB` | NoSQL database |
| Key Vault | `Microsoft.KeyVault` | Secrets management |

---

## Step 4: Azure Policy

**Azure Policy** enforces organizational standards and compliance at scale. It can **audit** or **deny** resource deployments that violate rules.

### Understanding Policies

- **Policy Definition**: The rule itself (e.g., "allowed locations")
- **Policy Assignment**: Applying a definition to a scope (subscription, resource group)
- **Policy Effect**: What happens when violated (Audit, Deny, Append, etc.)

### View Policy Assignments

```bash
# List all policy assignments in your subscription
az policy assignment list --output table

# Show details of a specific policy
az policy assignment show \
  --name "Allowed resource deployment regions" \
  --scope /subscriptions/<subscription-id>
```

### View Policy Parameters (Allowed Regions)

```bash
# Get the full policy assignment details including parameters
az policy assignment show \
  --name "Allowed resource deployment regions" \
  --scope /subscriptions/<subscription-id> \
  --output json | jq '.properties.parameters'
```

### Common Policy Restrictions You'll Encounter

1. **Allowed Locations**: Restricts which Azure regions you can deploy to
2. **Allowed Resource Types**: Limits which services can be created
3. **Required Tags**: Enforces tagging standards
4. **Allowed VM SKUs**: Restricts VM sizes to control costs
5. **Require Encryption**: Ensures storage/data is encrypted

### Example: Working Within Policy Constraints

If you hit this error:
```
RequestDisallowedByPolicy: Resource 'cafeweb' was disallowed by Azure
```

**Solution:**
1. Check policy assignments to find allowed regions
2. Use one of the allowed regions in your deployment command

```bash
# Find allowed locations in portal:
# Azure Portal → Policy → Assignments → Click on "Allowed resource deployment regions"

# Then deploy using an allowed region
az acr create \
  --name cafeweb \
  --resource-group cafe-web \
  --location southeastasia \
  --sku Standard
```

---

## Step 5: RBAC (Role-Based Access Control)

**RBAC** controls who can access Azure resources and what they can do with those resources.

### Key Concepts

- **Security Principal**: User, group, service principal, or managed identity
- **Role Definition**: Collection of permissions (e.g., Reader, Contributor, Owner)
- **Scope**: Level where access applies (subscription, resource group, resource)

### Built-in Roles

| Role | Permissions | Use Case |
|------|-------------|----------|
| **Owner** | Full access + can assign roles | Subscription admin |
| **Contributor** | Create/manage resources, can't assign roles | Developer |
| **Reader** | View resources only | Auditor |
| **User Access Administrator** | Manage user access only | IAM admin |

### View Role Assignments

```bash
# List role assignments for current user
az role assignment list --assignee $(az account show --query user.name -o tsv)

# List all assignments in a resource group
az role assignment list --resource-group my-resource-group --output table

# List all role definitions
az role definition list --output table
```

### Assign a Role

```bash
# Assign Contributor role to a user at resource group scope
az role assignment create \
  --assignee user@example.com \
  --role Contributor \
  --resource-group my-resource-group

# Assign Reader role at subscription scope
az role assignment create \
  --assignee user@example.com \
  --role Reader \
  --scope /subscriptions/<subscription-id>

# Assign role to a service principal
az role assignment create \
  --assignee <service-principal-id> \
  --role Contributor \
  --resource-group my-resource-group
```

### Remove a Role Assignment

```bash
az role assignment delete \
  --assignee user@example.com \
  --role Contributor \
  --resource-group my-resource-group
```

### Create Custom Roles

```json
{
  "Name": "Container Registry Reader",
  "Description": "Can pull images from ACR",
  "Actions": [
    "Microsoft.ContainerRegistry/registries/pull/read"
  ],
  "NotActions": [],
  "AssignableScopes": [
    "/subscriptions/<subscription-id>"
  ]
}
```

```bash
# Create custom role from JSON file
az role definition create --role-definition custom-role.json
```

---

## Step 6: Deploying Resources

Now that you've set up the foundation, you can deploy actual resources.

### Example: Azure Container Registry

```bash
# 1. Create resource group
az group create \
  --name cafe-web-rg \
  --location southeastasia

# 2. Register provider
az provider register --namespace Microsoft.ContainerRegistry

# 3. Wait for registration
az provider show \
  --namespace Microsoft.ContainerRegistry \
  --query "registrationState"

# 4. Create ACR
az acr create \
  --name cafeweb \
  --resource-group cafe-web-rg \
  --location southeastasia \
  --sku Standard

# 5. Login to ACR
az acr login --name cafeweb

# 6. Push an image
docker tag myapp:latest cafeweb.azurecr.io/myapp:latest
docker push cafeweb.azurecr.io/myapp:latest
```

### Example: Virtual Machine

```bash
# 1. Register required providers
az provider register --namespace Microsoft.Compute
az provider register --namespace Microsoft.Network

# 2. Create VM
az vm create \
  --resource-group my-resource-group \
  --name myVM \
  --image Ubuntu2204 \
  --admin-username azureuser \
  --generate-ssh-keys \
  --size Standard_B2s \
  --location southeastasia
```

### Example: Storage Account

```bash
# 1. Register provider
az provider register --namespace Microsoft.Storage

# 2. Create storage account (name must be globally unique)
az storage account create \
  --name mystorageacct12345 \
  --resource-group my-resource-group \
  --location southeastasia \
  --sku Standard_LRS \
  --kind StorageV2
```

---

## Common Errors and Solutions

### 1. RequestDisallowedByPolicy

**Error Message:**
```
(RequestDisallowedByPolicy) Resource 'cafeweb' was disallowed by Azure: 
This policy maintains a set of best available regions...
```

**Cause:** Trying to deploy to a region not allowed by Azure Policy

**Solution:**
```bash
# Check policy assignments
az policy assignment list --output table

# View allowed regions in portal
# Azure Portal → Policy → Assignments → "Allowed resource deployment regions"

# Deploy using an allowed region
az acr create --name cafeweb --resource-group cafe-web --location southeastasia --sku Standard
```

---

### 2. MissingSubscriptionRegistration

**Error Message:**
```
(MissingSubscriptionRegistration) The subscription is not registered 
to use namespace 'Microsoft.ContainerRegistry'
```

**Cause:** Resource provider not registered in subscription

**Solution:**
```bash
# Register the provider
az provider register --namespace Microsoft.ContainerRegistry

# Wait and verify
az provider show --namespace Microsoft.ContainerRegistry --query "registrationState"

# Retry deployment after status is "Registered"
```

---

### 3. AuthorizationFailed

**Error Message:**
```
(AuthorizationFailed) The client 'user@example.com' with object id '...' 
does not have authorization to perform action...
```

**Cause:** Missing RBAC permissions

**Solution:**
```bash
# Check current role assignments
az role assignment list --assignee $(az account show --query user.name -o tsv)

# Request Contributor or Owner role on the resource group
# (Admin needs to run this)
az role assignment create \
  --assignee user@example.com \
  --role Contributor \
  --resource-group my-resource-group
```

---

### 4. ResourceGroupNotFound

**Error Message:**
```
(ResourceGroupNotFound) Resource group 'my-resource-group' could not be found.
```

**Cause:** Resource group doesn't exist

**Solution:**
```bash
# Create the resource group first
az group create --name my-resource-group --location southeastasia
```

---

### 5. LocationNotAvailableForResourceType

**Error Message:**
```
The location 'eastus' is not available for resource type 
'Microsoft.ContainerRegistry/registries'
```

**Cause:** Service not available in selected region

**Solution:**
```bash
# Check which regions support the service
az provider show \
  --namespace Microsoft.ContainerRegistry \
  --query "resourceTypes[?resourceType=='registries'].locations"

# Use a supported region
az acr create --name cafeweb --location southeastasia ...
```

---

## Azure vs AWS Quick Reference

| Azure Concept | AWS Equivalent | Key Difference |
|---------------|----------------|----------------|
| Subscription | AWS Account | Billing boundary |
| Resource Group | CloudFormation Stack (loose) | Lifecycle container |
| Resource Provider Registration | N/A (services ready by default) | Must enable before use |
| Azure Policy | Service Control Policy (SCP) | Similar deny/audit mechanism |
| RBAC Role Assignment | IAM Policy Attachment | Scope-based inheritance |
| Management Group | Organizational Unit (OU) | Hierarchy structure |
| Azure AD Tenant | AWS Organization Root | Identity boundary |

### Workflow Comparison

**AWS:**
```
1. Create IAM user
2. Attach IAM policy
3. Deploy resource (service is ready)
```

**Azure:**
```
1. Create/Select subscription
2. Create resource group
3. Register resource provider
4. Check/comply with Azure Policy
5. Assign RBAC role
6. Deploy resource
```

---

## Best Practices

### 1. Naming Conventions

Use consistent naming across all resources:

```
<project>-<environment>-<region>-<resource-type>

Examples:
- myapp-prod-sea-rg (Resource Group)
- myapp-prod-sea-acr (Container Registry)
- myapp-prod-sea-vm (Virtual Machine)
- myapp-prod-sea-vnet (Virtual Network)
```

### 2. Tagging Strategy

Apply tags to all resources for cost tracking and organization:

```bash
az group create \
  --name myapp-prod-rg \
  --location southeastasia \
  --tags \
    Environment=Production \
    Project=MyApp \
    CostCenter=Engineering \
    Owner=team@example.com
```

### 3. Resource Group Organization

**Option A: By Environment**
```
myapp-dev-rg
myapp-staging-rg
myapp-prod-rg
```

**Option B: By Application Tier**
```
myapp-frontend-rg
myapp-backend-rg
myapp-database-rg
```

**Option C: By Lifecycle**
```
myapp-persistent-rg  (databases, storage)
myapp-compute-rg     (VMs, containers)
myapp-network-rg     (VNets, load balancers)
```

### 4. Security

- **Principle of Least Privilege**: Grant minimum required permissions
- **Use Managed Identities**: Avoid storing credentials in code
- **Enable Azure Security Center**: Get security recommendations
- **Use Key Vault**: Store secrets, keys, and certificates
- **Enable diagnostic logs**: Track who did what and when

### 5. Cost Management

```bash
# Set up budget alerts
az consumption budget create \
  --name monthly-budget \
  --amount 100 \
  --time-grain Monthly

# View cost analysis
az consumption usage list --output table

# Use cost-effective regions
# Southeast Asia is often cheaper than East US or West Europe
```

### 6. Resource Provider Pre-registration

For new subscriptions, register commonly used providers upfront:

```bash
# Common providers for web applications
providers=(
  "Microsoft.Compute"
  "Microsoft.Storage"
  "Microsoft.Network"
  "Microsoft.ContainerRegistry"
  "Microsoft.Web"
  "Microsoft.Sql"
  "Microsoft.KeyVault"
)

for provider in "${providers[@]}"; do
  echo "Registering $provider..."
  az provider register --namespace "$provider"
done
```

---

## Quick Start Checklist

Use this checklist for every new Azure project:

- [ ] **Subscription**: Verify you have access and set as default
- [ ] **Resource Group**: Create with appropriate naming and location
- [ ] **Resource Providers**: Register all needed providers
- [ ] **Azure Policy**: Check policy assignments and allowed regions
- [ ] **RBAC**: Ensure you have Contributor or Owner role
- [ ] **Tags**: Apply environment, project, and cost center tags
- [ ] **Deploy**: Create your resources
- [ ] **Verify**: Test that resources are working as expected
- [ ] **Monitor**: Set up alerts and diagnostic logging

---

## Useful Commands Reference

### Subscription Management
```bash
az account list --output table
az account show
az account set --subscription "Subscription-Name"
```

### Resource Groups
```bash
az group create --name <name> --location <region>
az group list --output table
az group delete --name <name> --yes --no-wait
```

### Resource Providers
```bash
az provider list --output table
az provider register --namespace <namespace>
az provider show --namespace <namespace>
```

### Policy
```bash
az policy assignment list --output table
az policy assignment show --name <name> --scope <scope>
```

### RBAC
```bash
az role assignment list --assignee <user-email>
az role assignment create --assignee <user> --role <role> --resource-group <rg>
az role definition list --output table
```

### General
```bash
az configure --defaults location=southeastasia group=my-resource-group
az find "vm create"
az --help
```

---

## Next Steps

Now that you understand the fundamentals:

1. **Deploy a sample application**: Try creating a web app, container, or VM
2. **Explore Azure DevOps**: Set up CI/CD pipelines
3. **Learn about networking**: VNets, subnets, NSGs, and load balancers
4. **Study security**: Key Vault, Managed Identities, and Azure AD
5. **Cost optimization**: Use Azure Advisor recommendations
6. **Infrastructure as Code**: Learn Bicep or Terraform for Azure

---

## Additional Resources

- [Azure CLI Documentation](https://learn.microsoft.com/en-us/cli/azure/)
- [Azure Quickstart Templates](https://github.com/Azure/azure-quickstart-templates)
- [Azure Architecture Center](https://learn.microsoft.com/en-us/azure/architecture/)
- [Azure Pricing Calculator](https://azure.microsoft.com/en-us/pricing/calculator/)
- [Azure Free Account](https://azure.microsoft.com/en-us/free/)

---

## Contributing

Found an error or want to add more examples? Feel free to submit a pull request or open an issue!

---

## License

This guide is open source and available under the MIT License.

---

**Happy Azure Learning! 🚀**