<?php

declare(strict_types=1);

namespace phpDocumentor\Guides\RestructuredText\Parser\Productions;

use phpDocumentor\Guides\Nodes\Node;
use phpDocumentor\Guides\Nodes\RawNode;
use phpDocumentor\Guides\RestructuredText\Parser\Buffer;
use phpDocumentor\Guides\RestructuredText\Parser\DocumentParserContext;

/** @implements Rule<RawNode> */
class CollectAllRule implements Rule
{

    public function applies(DocumentParserContext $documentParser): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function apply(DocumentParserContext $documentParserContext, ?Node $on = null): ?Node
    {
        $buffer = new Buffer();
        while ($documentParserContext->getDocumentIterator()->valid()) {
            $buffer->push($documentParserContext->getDocumentIterator()->current());
            $documentParserContext->getDocumentIterator()->next();
        }

        return new RawNode($buffer->getLinesString());
    }
}