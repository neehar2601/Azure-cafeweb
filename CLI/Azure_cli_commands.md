Azure CLI commands
structire
az <command_group> <action or command> <arguments> --option
    az is the base command that any Azure CLI command starts with.
    command group specifies the category or type of resource you're managing, like vm for virtual machines or storage for storage accounts.  
    action or command is the action you want to perform, such as create, list, or delete. 
    options are used ​to modify the command's behavior, ​for instance, specifying output formats or filters. ​

    few command groups
    resource managemnet for managing resource groups
    virtual machines(vm) to manage virtual machines
    network to manage network resources like Vnet,nsg etc
    storage to manage storage accounts,containers and blobs

    --help to get details about command, acytion or arguments
    --help-output
    --help-query


    example for create
    az group create
    az vm create
    az storage account create
    az network snet create


    lists:
    az resource list
    az account show
    az vm list
    az network vnet list --output table
     --querry to querry putput

    az interactive 
    azure cli interactive mode, browse command s and subcommand s with hits
    ctrl+d or exit to exit



    few examples:
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

usage 0f --query and grouping

## Step 7: Advanced Querying and Filtering

like running vm list and filtering the output

listing vms by size
resource usage
security audits


## interactive mode

setting scope
?? querry james path
for bash #and commands
powershell $psversiontable

​Imagine you're new to Azure CLI, ​tasked with navigating its vast array of commands. ​The syntax might seem complex, and finding the right command might feel overwhelming. ​What if you could simplify this learning process? ​Azure CLI interactive mode, or AZ interactive, ​offers a solution by simplifying command discovery, ​reducing errors, and helping you learn faster. ​With features like autocompletion, drop down suggestions, and built-in examples, ​Azure CLI interactive mode makes learning and using commands more intuitive. ​In this video, you'll explore how to use Azure interactive mode to ​enhance your command line experience. ​Before you begin, make sure you have an active Azure subscription. 
​Next, you have two flexible options for using the Azure CLI. ​Firstly, Azure cloud shell. ​This is an easy to use browser based option that doesn't require any setup. ​Secondly, local installation. ​This gives you more control and flexibility. ​You can install the Azure CLI on either command line or PowerShell seven, ​depending on your preference. ​To start Azure CLI in interactive mode, simply run the AZ interactive command. 
​This puts you in an interactive shell with autocompletion command descriptions and ​examples to guide you. ​Customize your interactive mode experience with the following key controls, ​turn descriptions and examples on or off using the f1 key. ​Additionally, if you want to control whether parameter defaults are shown, ​just press f2 to turn it on or off. ​Finally, for more control, press f3 to show or hide key gestures, ​giving you a fully customizable interactive experience. ​Interactive mode provides a simpler way to run a group of ​related commands in the Azure. ​Instead of typing out the full command every time, ​you can set a scope that focuses on a specific group of commands, ​putting you in control and saving you time. ​Let's explore how setting a scope works in practice. 
​Suppose you're working with virtual machines or VMs in Azure. ​Normally, you might run commands that require you to type vm each and ​every time, but there's a way to overcome this repetition. ​You can instead adjust your scope to the VM group level. ​Simply type az >> %%vm all your commands are now ​automatically in the VM group, saving you time and effort. ​What's more, you can focus on smaller groups within the VM group. ​For example, if you're dealing with VM images, ​you can type %% vm image to change the scope to vm image. ​However, since you're already in the VM scope, you'll just type %% image. 
​Ready to switch scopes. ​It's as easy as typing %%..to return to your previous scope, ​or just %% to return to the root scope in interactive mode. ​You can use a powerful tool called a James path query ​to search through the results of the last command you ran. ​This allows you to precisely filter and manage your data. ​To do this, you use double question marks followed by your query. ​For example, if you create a resource group and ​want to find its id, you would run the following commands. ​You can also use the result of one command as part of the following command. 
​For instance, if you listed all resource groups and ​then want to find something specific within them, you can use James ​path to filter through the results from the list you just generated. ​This command lists all the resources of type virtual machine ​on the first group, whose location is West Europe. ​Whether you're running bash or PowerShell commands, ​interactive mode has you covered. ​For bash scripts, use the hash followed by the command syntax. ​For PowerShell scripts, use standard PowerShell syntax. ​Another useful feature is the ability to quickly access examples of commands ​you've used so you can easily reference or replicate them as needed. ​To do this, scroll through examples using control + n for ​the next page and control + y for the previous page. 
​You can also view a specific example by using the ::# syntax. ​For instance, if you want to create a web app ​using a predefined configuration, ​you might type az >> webapp create::2. ​The second example for creating a web app, ​in this case using the web app create-G command, is then displayed. ​When using interactive mode within Azure CLI, ​upgrade to the latest version to access AI-driven enhancements. ​These features take your Azure CLI experience to the next level, ​helping you work smarter. ​You upgrade to the latest version by running the az extension add command, ​with the AI-driven command suggestions, simply run a command, ​then press the spacebar or move to the next step. ​The AI automatically generates a list of recommended commands to help ​you complete your task more efficiently. 
​For AI-powered scenario recommendations, make sure you've run a command first. ​After it runs successfully, press the spacebar. ​The AI will then suggest a variety of related command sets based on your ​current context, and you'll notice that whichever set you select, ​a double colon number is added after the space. ​To search for specific commands or scenarios, ​type a forward slash followed by a keyword like resource. ​This AI enhanced search quickly directs you to the commands and ​scenarios you need. ​Finally, if you prefer a more manual approach, you can easily disable ​AI suggestions in Azure CLI interactive mode using the az config set command. ​Azure CLI interactive mode transforms the way you work with Azure, ​making it easier to learn, execute, and master commands. 
​Whether you're navigating complex command structures or ​simply need to reduce errors. ​This tool helps you operate with greater efficiency and confidence. 