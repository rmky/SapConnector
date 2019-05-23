<?php
namespace exface\SapConnector\Widgets;

use exface\Core\Widgets\AbstractWidget;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\Widgets\CustomWidgetInterface;
use exface\Core\Interfaces\DataSources\DataSourceInterface;
use exface\Core\Factories\DataSourceFactory;
use exface\UI5Facade\Facades\Elements\UI5AbstractElement;
use exface\UI5Facade\Facades\UI5Facade;
use exface\SapConnector\Facades\Elements\UI5ITSmobile;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Interfaces\Widgets\iHaveContextualHelp;
use exface\Core\Widgets\Traits\iHaveContextualHelpTrait;

/**
 * 
 * @author Ralf Mulansky
 *
 */
class ITSmobile extends AbstractWidget implements CustomWidgetInterface, iHaveContextualHelp
{
    use iHaveContextualHelpTrait ;

    private $dataSourceAlias = null;
    
    private $dataSource = null;
    
    private $fKeys = [];
    
    private $fKeyBack = null;
    
    public function createFacadeElement(FacadeInterface $facade, $baseElement = null)
    {
        if ($facade instanceof UI5Facade) {
            return $this->createUI5Page($baseElement);
        }
        return $baseElement;
    }
    
    /***
     * 
     * @param string $selectorString
     * @return ITSmobile
     */
    public function setDataSourceAlias(string $selectorString) : ITSmobile
    {
        $this->dataSourceAlias = $selectorString;
        $this->dataSource = null;
        
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    public function getDataSourceAlias() : string
    {
        return $this->dataSourceAlias;
    }
    
    protected function getDataSource() : DataSourceInterface
    {
        if ($this->dataSource === null) {
            $this->dataSource = DataSourceFactory::createFromModel($this->getWorkbench(), $this->getDataSourceAlias());
        }
        return $this->dataSource;
    }
    
    protected function createUI5Page(UI5AbstractElement $ui5Element) : UI5ITSmobile
    {
        return new UI5ITSmobile($ui5Element->getWidget(), $ui5Element->getFacade());
    }
    
    /**
     * Set F-Key captions/function to be shown in ITSmobile view
     * 
     * @uxon-property f_keys
     * @uxon-type object
     * @uxon-template {"F1": "", "F2": "", "F3": "", "F4": "", "F5": "", "F6": "", "F7": "", "F8": "", "F9": "", "F10": "", "F11": "", "F12": ""}
     * @param UxonObject $uxon
     * @return ITSmobile
     */
    public function setFKeys(UxonObject $uxon) : ITSmobile
    {
        $this->fKeys = $uxon->toArray();
        return $this;
    }
    
    /**
     * Return the F-Key captions/functions array
     * 
     * @return array
     */
    public function getFKeys() : array
    {       
        $fKeys_default = [
            "F1" => '',
            "F2" => '',
            "F3" => '',
            "F4" => '',
            "F5" => '',
            "F6" => '',
            "F7" => '',
            "F8" => '',
            "F9" => '',
            "F10" => '',
            "F11" => '',
            "F12" => ''
        ];
        $new_fKeys = array_merge($fKeys_default, $this->fKeys);
        return $new_fKeys;
    }
    
    /**
     * Set the F-Key that has the function "Back" or similar in the specific ITSmobile app
     * 
     * @uxon-property f_key_back
     * @uxon-type string
     * @param string $fKeyBack
     * @return ITSmobile
     */
    public function setFKeyBack(string $fKeyBack) : ITSmobile
    {
        $this->fKeyBack = $fKeyBack;
        return $this;
    }
    
    /**
     * Returns the F-Key that has the function "Back"
     * 
     * @return string
     */
    public function getFKeyBack() : string
    {
        if ($this->fKeyBack === null) {
            $this->fKeyBack = "F7";
        }
        return $this->fKeyBack;
    }
    
    /**
     * 
     * @param string $keyWithF
     * @throws WidgetConfigurationError
     * @return int
     */
    public function getFKeyNumber(string $keyWithF) : int
    {
        if (strcasecmp(substr($keyWithF, 0, 1), 'f') !== 0) {
            throw new WidgetConfigurationError($this, 'Invalid F-Key "' . $keyWithF . '": F-Keys must start with an "F"!');
        }
        return substr($keyWithF, 1);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::getChildren()
     */
    public function getChildren() : \Iterator
    {
        foreach (parent::getChildren() as $child) {
            yield $child;
        }
        
        // Add the help button, so pages will be able to find it when dealing with the ShowHelpDialog action.
        // IMPORTANT: Add the help button to the children only if it is not hidden. This is needed to hide the button in
        // help widgets themselves, because otherwise they would produce their own help widgets, with - in turn - even
        // more help widgets, resulting in an infinite loop.
        if (! $this->getHideHelpButton()) {
            yield $this->getHelpButton();
        }
    }
    
    /**
     *
     * @return string
     */
    private function getButtonWidgetType()
    {
        return 'Button';
    }
}