<?php
namespace exface\SapConnector;

use exface\Core\CommonLogic\Model\App;
use exface\Core\Interfaces\InstallerInterface;
use exface\Core\Facades\AbstractHttpFacade\HttpFacadeInstaller;
use exface\Core\Factories\FacadeFactory;
use exface\SapConnector\Facades\ITSmobileProxyFacade;

class SapConnectorApp extends App
{
    public function getInstaller(InstallerInterface $injected_installer = null)
    {
        $installer = parent::getInstaller($injected_installer);
        
        $tplInstaller = new HttpFacadeInstaller($this->getSelector());
        $tplInstaller->setFacade(FacadeFactory::createFromString(ITSmobileProxyFacade::class, $this->getWorkbench()));
        $installer->addInstaller($tplInstaller);
        
        return $installer;
    }
}