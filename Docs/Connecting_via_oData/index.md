# Using SAP OData services as data sources

Any OData service in SAP NetWeaver can be used as a data source. The more it adheres to the OData standard, the easier it is to use it and the less configuration you will need - see detailed recommendations below.

If you want to use your data source to generate UI5/Fiori apps - refer to the documentation of the [OpenUI5Template](https://github.com/exface/OpenUI5Template/blob/master/Docs/index.md).

## Walkthrough

1. Create an app for your OData service - a separate app helps organize your models and is very advisable in most cases!
2. [Create a connection and a data source](setting_up_an_oData_data_source.md)
3. [Import the $metadata](generate_metamodel_from_odata.md) 
4. [Finetune the model](metamodel_finetuning.md) to handle difficult situations

## OData recommendations

**Most important:** stick to the ODataa standards and URL conventions!

- Each business object should have exactly one URL endpoint 
- Each URL should yield exaclty one business object type (eventually including expanded related objects)
- Be accurate in your $metadata - if an EntityType property is marked filterable, it should really be filterable!
- Make listing endpoints (EntitySet) as generic as possible to allow the UI designer to pick his filters and sorters without having to request code changes every time.