# Azure VNet Setup: Web + Database Network Topology

This guide describes how to build a robust **two-tier network topology** in Azure using the Azure CLI. This setup is ideal for web applications that require a clear separation between public-facing web servers and private database backends.

## 🏗️ Design Overview

### Architecture Components
- **Virtual Network (VNet)**: `AppVNet` — Address Space: `10.0.0.0/16`
- **Subnets**:
  - `WebSubnet` (`10.0.1.0/24`): Public-facing tier for web servers.
  - `DatabaseSubnet` (`10.0.2.0/24`): Dedicated private tier for databases.
- **Network Security Groups (NSGs)**:
  - `WebNSG`: Allows inbound HTTP (80) and HTTPS (443) from the internet. Restricts SSH (22) to specific IP ranges.
  - `DBNsg`: Strictly allows inbound traffic on the database port (e.g., 3306 for MySQL) only from the `WebSubnet`.

![VNet Architecture](https://devops-learner.s3.us-east-2.amazonaws.com/Azure-images/Vnet_deployment.png)

> [!NOTE]
> In Azure, a "public subnet" is simply a subnet where resources are assigned public IPs or sit behind a public Load Balancer. The actual security boundaries are enforced by NSGs.

---

## 🛠️ Prerequisites & Variables

### Prerequisites
- Azure CLI installed and authenticated (`az login`).
- An active Azure subscription with permissions to manage networking.

### Configuration Variables
Adjust these variables in your shell before running the commands:

```bash
# General
RG_NAME="rg-web-db-demo"
LOCATION="eastus"

# Networking
VNET_NAME="AppVNet"
VNET_CIDR="10.0.0.0/16"
WEB_SUBNET_NAME="WebSubnet"
WEB_SUBNET_CIDR="10.0.1.0/24"
DB_SUBNET_NAME="DatabaseSubnet"
DB_SUBNET_CIDR="10.0.2.0/24"

# NSGs
WEB_NSG_NAME="WebNSG"
DB_NSG_NAME="DBNsg"

# Security
MY_IP_CIDR="X.X.X.X/32"      # Your IP address for SSH access
DB_PORT="3306"               # Port for your database (e.g., 3306 for MySQL, 1433 for SQL Server)
```

---

## 🚀 Deployment Steps

### 1. Create Resource Group
```bash
az group create \
  --name "$RG_NAME" \
  --location "$LOCATION"
```

### 2. Create VNet and Subnets
Create the VNet and the initial Web subnet:
```bash
az network vnet create \
  --resource-group "$RG_NAME" \
  --name "$VNET_NAME" \
  --address-prefixes "$VNET_CIDR" \
  --subnet-name "$WEB_SUBNET_NAME" \
  --subnet-prefix "$WEB_SUBNET_CIDR"
```

Create the secondary Database subnet:
```bash
az network vnet subnet create \
  --resource-group "$RG_NAME" \
  --vnet-name "$VNET_NAME" \
  --name "$DB_SUBNET_NAME" \
  --address-prefixes "$DB_SUBNET_CIDR"
```

### 3. Create Network Security Groups (NSGs)
```bash
# Create NSG for the Web tier
az network nsg create \
  --resource-group "$RG_NAME" \
  --name "$WEB_NSG_NAME" \
  --location "$LOCATION"

# Create NSG for the Database tier
az network nsg create \
  --resource-group "$RG_NAME" \
  --name "$DB_NSG_NAME" \
  --location "$LOCATION"

![NSG Creation](https://devops-learner.s3.us-east-2.amazonaws.com/Azure-images/NSG_creation.png)
```

### 4. Configure Security Rules

#### Web Tier Rules
Allow HTTP/HTTPS traffic from any source:
```bash
az network nsg rule create \
  --resource-group "$RG_NAME" \
  --nsg-name "$WEB_NSG_NAME" \
  --name Allow-HTTP --priority 100 --destination-port-ranges 80

az network nsg rule create \
  --resource-group "$RG_NAME" \
  --nsg-name "$WEB_NSG_NAME" \
  --name Allow-HTTPS --priority 110 --destination-port-ranges 443
```

Restrict SSH access to your specific IP:
```bash
az network nsg rule create \
  --resource-group "$RG_NAME" \
  --nsg-name "$WEB_NSG_NAME" \
  --name Allow-SSH-From-MyIP \
  --priority 120 \
  --source-address-prefixes "$MY_IP_CIDR" \
  --destination-port-ranges 22
```

#### Database Tier Rules
Only allow SQL traffic originating from the `WebSubnet`:
```bash
az network nsg rule create \
  --resource-group "$RG_NAME" \
  --nsg-name "$DB_NSG_NAME" \
  --name AllowMySQLFromWebSubnet \
  --protocol Tcp \
  --direction Inbound \
  --source-address-prefixes "$WEB_SUBNET_CIDR" \
  --source-port-range '*' \
  --destination-address-prefixes "$DB_SUBNET_CIDR" \
  --destination-port-range "$DB_PORT" \
  --access Allow \
  --priority 200

![NSG Deployment](https://devops-learner.s3.us-east-2.amazonaws.com/Azure-images/DBNSG.png)
```
Azure NSGs don’t support “security-group-as-source/destination” the way AWS security groups do.

What Azure supports instead
In NSG rules you can only use:
```
  IP/CIDR: 10.0.1.0/24, 10.0.1.4/32
  Default tags: VirtualNetwork, Internet, AzureLoadBalancer
  Service tags: Sql, Storage, AzureMonitor, etc.
```
You cannot reference another NSG’s name or ID in source-address-prefixes or destination-address-prefixes

### 5. Associate NSGs with Subnets
```bash
# Link Web NSG to Web Subnet
az network vnet subnet update \
  --resource-group "$RG_NAME" \
  --vnet-name "$VNET_NAME" \
  --name "$WEB_SUBNET_NAME" \
  --network-security-group "$WEB_NSG_NAME"

# Link DB NSG to Database Subnet
az network vnet subnet update \
  --resource-group "$RG_NAME" \
  --vnet-name "$VNET_NAME" \
  --name "$DB_SUBNET_NAME" \
  --network-security-group "$DB_NSG_NAME"

![NSG Subnet Association](https://devops-learner.s3.us-east-2.amazonaws.com/Azure-images/NSG_SubNet_Assosciation.png)
```

---

## 🔍 Verification

List all subnets and their associated NSGs:
```bash
az network vnet subnet list \
  --resource-group "$RG_NAME" \
  --vnet-name "$VNET_NAME" \
  --output table
```

Verify specific NSG rules:
```bash
az network nsg rule list --resource-group "$RG_NAME" --nsg-name "$WEB_NSG_NAME" --output table
az network nsg rule list --resource-group "$RG_NAME" --nsg-name "$DB_NSG_NAME" --output table
```

---

## ⏭️ Next Steps
1. **Compute Deployment**: Place your web server (VM, ACI, or VMSS) in the `WebSubnet` and your database in the `DatabaseSubnet`.
2. **Connectivity**: The database will be reachable from the web tier via its internal IP on the specified port, but will remain hidden from the public internet.
3. **Advanced Setup**: Consider adding a **Public Load Balancer** or an **Azure Bastion** host for more secure administration.
