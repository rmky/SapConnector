<?php
namespace exface\SapConnector\Actions;

use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Actions\iCallService;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use GuzzleHttp\Psr7\Request;
use exface\UrlDataConnector\Interfaces\HttpConnectionInterface;
use exface\UrlDataConnector\Psr7DataQuery;
use Psr\Http\Message\ResponseInterface;
use exface\Core\Factories\ResultFactory;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\CommonLogic\Actions\ServiceParameter;
use exface\Core\Interfaces\Actions\ServiceParameterInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Calls a SOAP service operation.
 * 
 * @author Ralf Mulansky
 *
 */
class CallRfcViaSoap extends AbstractAction implements iCallService 
{
    private $serviceName = null;
    
    private $httpMethod = 'POST';
    
    private $operation = null;
    
    private $parameters = [];
    
    private $result_message_parameter_name = null;
    
    private $error_message = null;
    
    protected function init()
    {
        parent::init();
        // TODO name, icon
    }
    
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction): ResultInterface
    {
        $input = $this->getInputDataSheet($task);
        
        $body = $this->buildRequestBodyEnvelope($this->getSoapOperation(), $this->buildBodyParams($input));
        $request = new Request($this->getHttpMethod(), $this->buildUrl($input), $this->buildRequestHeader(), $body);
        $query = new Psr7DataQuery($request);
        $response = $this->getDataConnection()->query($query)->getResponse();
        $resultData = $this->parseResponse($response);
        
        return ResultFactory::createDataResult($task, $resultData, $this->getResultMessageText() ?? $this->buildResultMessage($response));
    }
    
    protected function buildRequestBodyEnvelope(string $operation, string $params) : string
    {
        return <<<XML
        
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:sap-com:document:sap:soap:functions:mc-style">
   <soapenv:Header/>
   <soapenv:Body>
      <urn:{$operation}>
            {$params}
      </urn:{$operation}>
   </soapenv:Body>
</soapenv:Envelope>

XML;
    }
            
    protected function buildBodyParams(DataSheetInterface $data) : string
    {
        $params = '';
        foreach ($this->getParameters() as $param) {
            $val = $data->getCellValue($param->getName(), 0);
            $params .= '<' . $param->getName() . '>' . $this->prepareParamValue($param, $val) . '</' . $param->getName() . '>';
        }
        return $params;
    }
    
    protected function buildRequestHeader() : array
    {
        return array(
            "Content-Type" => "text/xml"
        );
    }
    
    protected function buildUrl(DataSheetInterface $data) : string
    {
        $url = $this->getServiceName() . '?';
        return $url;
    }
    
    protected function buildResultMessage(ResponseInterface $response) : string
    {
        $crawler = new Crawler($response->getBody()->__toString());
        $resultmsg = $crawler->filter($this->getResultMessageParameterName())->text();
        if ($resultmsg == null) { 
            return $this->getWorkbench()->getApp('exface.SapConnector')->getTranslator()->translate('SOAPSERVICECALLSUCCESSFUL');
        }
        elseif ($this->error_message !== null) {
            //$resultmsg .= $this->error_message;
            return $this->getWorkbench()->getApp('exface.SapConnector')->getTranslator()->translate($this->error_message);
        }
        else {
            return $this->getWorkbench()->getApp('exface.SapConnector')->getTranslator()->translate($resultmsg);
        }
    }
          
    protected function prepareParamValue(ServiceParameterInterface $parameter, $val) : string
    {
        if ($parameter->hasDefaultValue() === true && $val === null) {
            $val = $parameter->getDefaultValue();
        }
        if ($val == null && $parameter->isRequired() === true) {
            $value = 'Pflichtparameter ' . $parameter->getName() .  ' nicht angegeben';
            $this->setErrorMessage($value);
        }
        if ($parameter->isEmpty() === true){
            $val = '';
        }
        //$val = $parameter->getDataType()->parse($val);
        return  $val;
    }
 
    protected function parseResponse(ResponseInterface $response) : DataSheetInterface
    {
        $ds = DataSheetFactory::createFromObject($this->getResultObject());
        $ds->setAutoCount(false);
        
        $crawler = new Crawler($response->getBody()->__toString());
        $params = $crawler->filter($this->getMetaObject()->getAlias())->children();
        foreach ($params as $paramCrawler) {
            $attrName = $paramCrawler->nodeName;
            $attrVal = $paramCrawler->textContent;
            
            foreach ($this->getMetaObject()->getAttributes()->getReadable() as $attr) {
                if (strcasecmp($attrName, $attr->getDataAddress()) === 0) {
                    $ds->setCellValue($attr->getDataAddress(), 0, $attrVal);
                }
            }  
        }
        if ($response->getStatusCode() == 200) {
            return $ds->setFresh(true);
        }
        return $ds;
    }
    
    protected function getResultObject() : MetaObjectInterface
    {
        if ($this->hasResultObjectRestriction()) {
            return $this->getResultObjectExpected();
        }
        return $this->getMetaObject();
    }
    
    protected function getDataConnection() : HttpConnectionInterface
    {
        return $this->getMetaObject()->getDataConnection();
    }
    
    /**
     *
     * @return string
     */
    public function getResultMessageParameterName() : string
    {
        return $this->result_message_parameter_name;
    }
    
    /**
     * The XML node for the resultmessage .
     *
     * @uxon-property result_message_parameter_name
     * @uxon-type string
     *
     * @param string $value
     * @return CallRfcViaSoap
     */
    public function setResultMessageParameterName(string $value) : CallRfcViaSoap
    {
        $this->result_message_parameter_name = $value;
        return $this;
    }
    
    /**
     *
     * @return string
     */
    public function getServiceName() : string
    {
        return $this->serviceName;
    }
    
    /**
     * The name for the operation.
     * 
     * @uxon-property service_name
     * @uxon-type string
     * 
     * @param string $value
     * @return CallRfcViaSoap
     */
    public function setServiceName(string $value) : CallRfcViaSoap
    {
        $this->serviceName = $value;
        return $this;
    }
    
    /**
     *
     * @return string
     */
    public function getHttpMethod() : string
    {
        return $this->httpMethod;
    }
    
    /**
     * 
     * @param string $value
     * @return CallRfcViaSoap
     */
    public function setHttpMethod(string $value) : CallRfcViaSoap
    {
        $this->httpMethod = $value;
        return $this;
    }
    
    /**
     *
     * @return UxonObject
     */
    public function getParameters() : array
    {
        return $this->parameters;
    }
    
    /**
     * Defines parameters supported by the service.
     * 
     * @uxon-property parameters
     * @uxon-type \exface\Core\CommonLogic\Actions\ServiceParameter[]
     * @uxon-template [{"name": ""}]
     * 
     * @param UxonObject $value
     * @return CallRfcViaSoap
     */
    public function setParameters(UxonObject $uxon) : CallRfcViaSoap
    {
        foreach ($uxon as $paramUxon) {
            $this->parameters[] = new ServiceParameter($this, $paramUxon);
        }
        return $this;
    }
    
    public function getParameter(string $name) : ServiceParameterInterface
    {
        foreach ($this->getParameters() as $arg) {
            if ($arg->getName() === $name) {
                return $arg;
            }
        }
    }
    
    /**
     *
     * @return string
     */
    public function getSoapOperation() : string
    {
        return $this->operation;
    }
    
    /**
     * SOAP operation name
     * 
     * @uxon-property soap_operation
     * @uxon-type string
     * 
     * @param string $value
     * @return CallRfcViaSoap
     */
    public function setSoapOperation(string $value) : CallRfcViaSoap
    {
        $this->operation = $value;
        return $this;
    }
    
    protected function setErrorMessage(string $value) : CallRfcViaSoap
    {
        $this->error_message = $value;
        return $this;
    }
    
    protected function getErrorMessage()
    {
        return $this->error_message;
    }
}
?>