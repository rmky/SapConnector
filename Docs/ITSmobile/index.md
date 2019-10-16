# Integrating ITSmobile applications as widgets

You can easily integrate existing ITSmobile applications into your app without changing anything in SAP.

**NOTE:** This currenlty only works with the UI5 facade!

## Connecting an ITSmobile service

The custom widget `exface.SapConnector.ITSmobile` can be used to integrate an ITSmobile app into 
a facade. It is basically a remote control for the ITSmobile app and a visual transformation layer to make 
the app look and feel as an integral part of the facade. 

Under the hood, the `ITSmobileProxyFacade` is used as middleware between the web browser
and SAP. All web requests are routed through this proxy, which also takes care of
authentication, CORS, etc.

To use this widget, you will need a separate data source for every ITSmobile app with: 

- the `SapITSmobileConnector`, where the `url` points to the ITSmobile web service,
- the `DummyQueryBuilder`

You will also need a dummy meta object because every widget requires a meta object. In fact, you 
can use any existing object or create a new one for your ITSmobile data source. An empty object
without attributes and marked as neither readable nor writable will do fine. The data source cannot 
be used for reading or writig data directly. The entire logic remains in SAP - it's just the 
appearance, that is being enhanced by this widget.

## Examples

Place this code in a page's root or in a container like a `Dialog` or `SplitVertical`. 

```
{
 "widget_type": "exface.SapConnector.ITSmobile",
 "data_source_alias": "POWERUI_DEMO_ITSMOBILE",
 "object_alias": "powerui.DemoMES.basis_objekt",
 "f_key_back": "F7",
 "f_keys": {
   "F1": "Help",
   "F7": "Go Back"
 }
}

```

Note the properties `f_key_back` and `f_keys`: since ITSmobile apps are often controlled by hardware 
keys, this widget provides softkey alternatives. These softkeys can be labeled using the `f_keys`
configuration. There is also a dedicated back-softkey, so `f_key_back` let's you define, which
F-key it should "press".

If a screen contains buttons labeled with `Fxx` (e.g. "F2 List"), the corresponding F-softkey will
automatically get the label of the button on that screen. It will change back to it's initial
value when leaving the screen.

## Using ITSmobile themes other than 99

TODO