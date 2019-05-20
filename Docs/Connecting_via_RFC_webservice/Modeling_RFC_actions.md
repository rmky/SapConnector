# Modeling RFC actions

To call an RFC function module with a button, we will need a meta object describing parameters of the RFC and an action model attached to it.

## Example

Concider this example function module definition:

```
  IMPORTING
     VALUE(IV_LENUM) TYPE  CHAR20
     VALUE(IV_PLATZ_ZIEL) TYPE  CHAR20
     VALUE(IV_BUCHEN) TYPE  CHAR1
  EXPORTING
     VALUE(EV_MATNR) TYPE  CHAR20
     VALUE(EV_MENGE) TYPE  QUA_VALCOM
     VALUE(EV_MEINS) TYPE  MEINS
     VALUE(EV_MAKTX) TYPE  MAKTX
     VALUE(EV_LENUM) TYPE  CHAR20
     VALUE(EV_PLATZ_ZIEL) TYPE  CHAR20
     VALUE(EV_MSGTY) TYPE  SYMSGTY
     VALUE(EV_MSGTXT) TYPE  BAPI_MSG

```

While this is the definition of the function module in SAP, the web service is defined by the WSDL generated automatically, when publishing the function module. You can see the URL of the WSDL in the properties of the generated service. Note, that the WSDL uses camelCase syntax for the parameters.

Thus, our meta object should have an attribute for every parameter needed with the camelCased parameter name as data address: e.g. `IvLenum` for `IV_LENUM`. The importing parameters should be marked editable and writable, but not readable, and the exporting parameter the other way around.

Let's assume, the importing parameters `ÌV_PLATZ_ZIEL` and `IV_BUCHEN` are optional, but `IV_BUCHEN` can only be used with the other two parameters set. This means, we have three possible combinations of parameters to call the RFC: `IV_LENUM` only, `IV_LENUM` + `IV_PLATZ_ZIEL` and all three together. In general, it is a good idea to create three actions here - one for each parameter set. This allows us to describe in the metamodel, what exactly each combination does, which makes corresponding buttons and contextual help much better understandable for users.

The first action would have the following configuration:

```
{
 "service_name": "ServiceNameInWSDL",
 "soap_operation": "NameOfOperationInWSDL",
 "result_message_parameter_name": "EvMsgtxt",
 "parameters": [
   {
     "name": "IvLenum"
   },
   {
     "name": "IvPlatzZiel",
     "empty": true
   },
   {
     "name": "IvBuchen",
     "empty": true
   }
 ]
}

```

The second one would not have the `empty` property for the second parameter and the third one would not have `empty` parameters at all.

Note, that the exporting parameter `EV_MSGTXT` contains a human-readable response text in our example. We can make our action use it as it's result message by setting `result_message_parameter_name`.

## Data types

In this example, we did not specify the data types of the parameters in the model. This means, they will all be treated as generic strings. If you wish to have type checks _before_ the SOAP request is sent, configure appropriate data types for every attribute and/or every parameter. Data types for attributes will give you type-specific input widgets and type-validation in the UI, while data types for parameters (i.e. `data_type` in the above `parameters` definition) will give you type validation right before the SOAP request is made - regardles of where the input data came from.