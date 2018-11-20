# SAP Connector

The SAP connector offers multiple options to use SAP systems as data sources.

## UsingOData services

oData web servcies are the preferred data sources for SAP UI5 (Fiori) apps. Being highly standardized RESTfull services, they are easy to import into the meta model. However, since the backend of eachOData service is a custom implementation in SAP NetWeaver, the ease of use, performance, etc. largely depend to that implementation.

Here is what you will need:

1. Create anOData service in SAP NetWeaver (see SAP docs)
2. Create an app for yourOData service in the metamodel - a separate app helps organize your models and is very advisable in most cases!
3. [Set up a data source with anOData connection in the metamodel](Connecting_via_oData/setting_up_an_oData_data_source.md)
4. [Generate a metamodel by importing the $metadata document](Connecting_via_oData/generate_metamodel_from_odata.md)

Refer to the [SAPOData connector documentation](Connecting_via_oData/index.md) for more information.

## Calling RFC function modules or BAPIs via SOAP web service

Don't likeOData? There is a possibility to access plain old RFC function modules and even some BAPIs by configuring a SOAP webservice for them. Here is what you will need:

1. [Set up the webserivce in SAP NetWeaver](Connecting_via_RFC_webservice/setting_up_rfc_webservice.md)
2. Set up a data source and a connection in the metamodel
3. Import the WSDL into the metamodel

## SAP HANA ODBC connector

If your are using SAP HANA, you can access it directly via SQL. Choosing this option will speed up app development enormously, but it should only be used for custom data structures, that have no relation to SAP applications.