# Setting up a SAP OData connection and data source

Each OData service should be modeled as a sperate data source. The $metadata document can be imported and used to generate the corresponding metamodel automatically.

## Creating a data connection

Go to Administration > Metamodel > Connections and add a new connection as shown below.

![SAP OData connection settings](images/northwind_connection.png)

If your service includes SAP-specific annotations (e.g. options like `sap:label` in the $metamodel), use the `SapODataConnector` - otherwise use the default `ODataConnector`. As a rule of thumb, all OData services on a SAP NetWeaver will have the SAP-annotations. 

The only mandatory configuration option is the `url`, which should point to the root of your OData service. Make sure, it ends with a slash (e.g. `http://services.odata.org/V4/Northwind/Northwind.svc/`). The connector does not depend on the version of the OData standard, that is being used.

The name and alias of your connection can be anything - refer to the general [data source documentation](https://github.com/exface/Core/blob/master/Docs/understanding_the_metamodel/data_sources_and_connections.md) for details.

If you have created an app for your OData metamodel, don't forget to assign it to the connection.

## Creating a data source

Now it's time to create a data source for our model. Proceed to Administration > Metamodel > Data Sources and add a new entry as shown below using the connection created in the previous step as default data connection.

![SAP OData data source settings](images/northwind_data_source.png)

Again, depending on the use SAP-specific annotations, choose either `SapOData2JsonUrlBuilder` or `OData2JsonUrlBuilder`. Make sure, the OData version of the query builder matches the version of your service. Most SAP OData services will use OData v2.

## Generating the metamodel

In the [next tutorial](generate_metamodel_from_odata.md), we will use the OData $metadata document to generate a metamodel. 



