<?php declare(strict_types=1);

namespace Convo\Pckg\Destiny\Processors;

use Convo\Core\Workflow\IConversationProcessor;
use Convo\Core\Workflow\IConvoRequest;
use Convo\Core\Workflow\IConvoResponse;
use Convo\Core\Workflow\IRequestFilterResult;
use Convo\Pckg\Core\Processors\AbstractServiceProcessor;

/**
 * This processor deals with transactions between characters and the vault.
 */
class InventoryManagementProcessor extends AbstractServiceProcessor implements IConversationProcessor
{
    public function __construct($properties, $packageProviderFactory, $destinyApiFactory, $service)
    {
        
    }

    public function process(IConvoRequest $request, IConvoResponse $response, IRequestFilterResult $result) { }
    
}