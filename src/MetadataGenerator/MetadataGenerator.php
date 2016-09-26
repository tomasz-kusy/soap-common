<?php
namespace GoetasWebservices\SoapServices\SoapCommon\MetadataGenerator;

use Doctrine\Common\Inflector\Inflector;
use GoetasWebservices\XML\SOAPReader\Soap\Operation;
use GoetasWebservices\XML\SOAPReader\Soap\OperationMessage;
use GoetasWebservices\XML\SOAPReader\Soap\Service;
use GoetasWebservices\XML\WSDLReader\Wsdl\Message\Part;
use GoetasWebservices\XML\WSDLReader\Wsdl\PortType\Param;
use GoetasWebservices\Xsd\XsdToPhp\Naming\NamingStrategy;

class MetadataGenerator implements MetadataGeneratorInterface
{
    protected $namespaces = [];

    protected $alternativeEndpoints = [];

    private $baseNs = [
        'headers' => '\\SoapEnvelope\\Headers',
        'parts' => '\\SoapEnvelope\\Parts',
        'messages' => '\\SoapEnvelope\\Messages',
    ];
    /**
     * @var NamingStrategy
     */
    private $namingStrategy;
    /**
     * @var bool
     */
    private $unwrap = false;

    /**
     * @param NamingStrategy $namingStrategy
     * @param array $namespaces
     */
    public function __construct(NamingStrategy $namingStrategy, array $namespaces)
    {
        $this->namespaces = $namespaces;
        $this->namingStrategy = $namingStrategy;
    }

    public function addAlternativeEndpoint($service, $port, $endPoint)
    {
        $this->alternativeEndpoints[$service][$port] = $endPoint;
    }

    public function setUnwrap($mode = true)
    {
        $this->unwrap = (bool)$mode;
    }

    public function setBaseNs($baseNs = array())
    {
        foreach ($baseNs as $k => $ns) {
            if (isset($this->baseNs[$k])) {
                $this->baseNs[$k] = $ns;
            }
        }
    }

    /**
     * @param Service[] $soapServices
     * @return array
     */
    public function generate(array $soapServices)
    {
        $services = [];
        foreach ($soapServices as $soapService) {
            $port = $soapService->getPort();

            $endpoint = isset($this->alternativeEndpoints[$port->getService()->getName()][$port->getName()]) ? $this->alternativeEndpoints[$port->getService()->getName()][$port->getName()] : $soapService->getAddress();

            $services[$port->getService()->getName()][$port->getName()] = [
                'operations' => $this->generateService($soapService),
                'unwrap' => $this->unwrap,
                'endpoint' => $endpoint
            ];

        }
        return $services;
    }

    protected function generateService(Service $service)
    {
        $operations = [];

        foreach ($service->getOperations() as $operation) {
            $operations[$operation->getOperation()->getName()] = $this->generateOperation($operation);
        }
        return $operations;
    }

    protected function generateOperation(Operation $soapOperation)
    {

        $operation = [
            'action' => $soapOperation->getAction(),
            'style' => $soapOperation->getStyle(),
            'name' => $soapOperation->getOperation()->getName(),
            'method' => Inflector::camelize($soapOperation->getOperation()->getName()),
            'input' => $this->generateInOut($soapOperation, $soapOperation->getInput(), $soapOperation->getOperation()->getPortTypeOperation()->getInput(), 'Input'),
            'output' => $this->generateInOut($soapOperation, $soapOperation->getOutput(), $soapOperation->getOperation()->getPortTypeOperation()->getOutput(), 'Output'),
            'fault' => []
        ];

        /**
         * @var $fault \GoetasWebservices\XML\SOAPReader\Soap\Fault
         */

        foreach ($soapOperation->getFaults() as $fault) {
            //$operation['fault'][$fault->getName()] = $fault->get;
            // @todo do faults metadata
        }

        return $operation;
    }

    protected function generateInOut(Operation $operation, OperationMessage $operationMessage, Param $param, $direction)
    {
        $xmlNs = $operation->getOperation()->getDefinition()->getTargetNamespace();
        if (!isset($this->namespaces[$xmlNs])) {
            throw new \Exception("Can not find a PHP namespace to be associated with '$xmlNs' XML namespace");
        }
        $ns = $this->namespaces[$xmlNs];
        $operation = [
            'message_fqcn' => $ns
                . $this->baseNs['messages'] . '\\'
                . Inflector::classify($operationMessage->getMessage()->getOperation()->getName())
                . $direction,
            'headers_fqcn' => $ns
                . $this->baseNs['headers'] . '\\'
                . Inflector::classify($operationMessage->getMessage()->getOperation()->getName())
                . $direction,
            'part_fqcn' => $ns
                . $this->baseNs['parts'] . '\\'
                . Inflector::classify($operationMessage->getMessage()->getOperation()->getName())
                . $direction,
            'parts' => $this->getParts($param->getMessage()->getParts())
        ];

        return $operation;
    }

    /**
     * @param Part[] $messageParts
     * @return array
     */
    private function getParts(array $messageParts)
    {
        $parts = [];
        foreach ($messageParts as $partName => $part) {
            $partName = $this->namingStrategy->getPropertyName($part);
            if ($part->getType()) {
                $parts [$partName] = $this->namingStrategy->getTypeName($part->getType());
            } else {
                $parts [$partName] = $this->namingStrategy->getItemName($part->getElement());
            }
        }
        return $parts;
    }
}
