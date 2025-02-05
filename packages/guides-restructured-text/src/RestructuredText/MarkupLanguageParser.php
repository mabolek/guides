<?php

declare(strict_types=1);

namespace phpDocumentor\Guides\RestructuredText;

use InvalidArgumentException;
use phpDocumentor\Guides\MarkupLanguageParser as ParserInterface;
use phpDocumentor\Guides\Nodes\DocumentNode;
use phpDocumentor\Guides\ParserContext;
use phpDocumentor\Guides\RestructuredText\Directives\AdmonitionDirective;
use phpDocumentor\Guides\RestructuredText\Directives\BestPracticeDirective;
use phpDocumentor\Guides\RestructuredText\Directives\CautionDirective;
use phpDocumentor\Guides\RestructuredText\Directives\ClassDirective;
use phpDocumentor\Guides\RestructuredText\Directives\Code;
use phpDocumentor\Guides\RestructuredText\Directives\CodeBlock;
use phpDocumentor\Guides\RestructuredText\Directives\ContainerDirective;
use phpDocumentor\Guides\RestructuredText\Directives\Directive;
use phpDocumentor\Guides\RestructuredText\Directives\Figure;
use phpDocumentor\Guides\RestructuredText\Directives\HintDirective;
use phpDocumentor\Guides\RestructuredText\Directives\Image;
use phpDocumentor\Guides\RestructuredText\Directives\ImportantDirective;
use phpDocumentor\Guides\RestructuredText\Directives\IncludeDirective;
use phpDocumentor\Guides\RestructuredText\Directives\IndexDirective;
use phpDocumentor\Guides\RestructuredText\Directives\Meta;
use phpDocumentor\Guides\RestructuredText\Directives\NoteDirective;
use phpDocumentor\Guides\RestructuredText\Directives\RawDirective;
use phpDocumentor\Guides\RestructuredText\Directives\Replace;
use phpDocumentor\Guides\RestructuredText\Directives\RoleDirective;
use phpDocumentor\Guides\RestructuredText\Directives\SeeAlsoDirective;
use phpDocumentor\Guides\RestructuredText\Directives\SidebarDirective;
use phpDocumentor\Guides\RestructuredText\Directives\TipDirective;
use phpDocumentor\Guides\RestructuredText\Directives\Title;
use phpDocumentor\Guides\RestructuredText\Directives\Toctree;
use phpDocumentor\Guides\RestructuredText\Directives\TopicDirective;
use phpDocumentor\Guides\RestructuredText\Directives\Uml;
use phpDocumentor\Guides\RestructuredText\Directives\WarningDirective;
use phpDocumentor\Guides\RestructuredText\Directives\Wrap;
use phpDocumentor\Guides\RestructuredText\Parser\DocumentParserContext;
use phpDocumentor\Guides\RestructuredText\Parser\Productions\DocumentRule;
use phpDocumentor\Guides\RestructuredText\Parser\Productions\Rule;
use phpDocumentor\Guides\RestructuredText\Span\SpanParser;
use phpDocumentor\Guides\RestructuredText\Toc\GlobSearcher;
use phpDocumentor\Guides\RestructuredText\Toc\ToctreeBuilder;
use phpDocumentor\Guides\UrlGenerator;
use RuntimeException;
use Webmozart\Assert\Assert;

use function strtolower;

class MarkupLanguageParser implements ParserInterface
{
    private ?ParserContext $parserContext = null;

    /** @var Directive[] */
    private array $directives = [];

    private ?string $filename = null;

    private ?DocumentParserContext $documentParser = null;

    /** @var Rule<DocumentNode> */
    private Rule $startingRule;

    /**
     * @param iterable<Directive> $directives
     * @param Rule<DocumentNode> $startingRule
     */
    public function __construct(
        Rule $startingRule,
        iterable $directives
    ) {
        foreach ($directives as $directive) {
            $this->registerDirective($directive);
        }

        $this->startingRule = $startingRule;
    }

    public static function createInstance(): self
    {
        $spanParser = new SpanParser();

        $directives = [
            new AdmonitionDirective(),
            new BestPracticeDirective(),
            new CautionDirective(),
            new ClassDirective(),
            new Code(),
            new CodeBlock(),
            new ContainerDirective(),
            new Figure(new UrlGenerator()),
            new HintDirective(),
            new Image(new UrlGenerator()),
            new ImportantDirective(),
            new IncludeDirective(),
            new IndexDirective(),
            new Meta(),
            new NoteDirective(),
            new RawDirective(),
            new Replace($spanParser),
            new RoleDirective(),
            new SeeAlsoDirective(),
            new SidebarDirective(),
            new TipDirective(),
            new Title(),
            new Toctree(
                new ToctreeBuilder(
                    new GlobSearcher(new UrlGenerator()),
                    new UrlGenerator()
                )
            ),
            new TopicDirective(),
            new Uml(),
            new WarningDirective(),
            new Wrap(),
        ];

        $documentRule = new DocumentRule($directives);

        return new self($documentRule, $directives);
    }

    public function supports(string $inputFormat): bool
    {
        return strtolower($inputFormat) === 'rst';
    }

    /** @deprecated one should use injected rules in a rule. Not subparsers */
    public function getSubParser(): MarkupLanguageParser
    {
        return new MarkupLanguageParser(
            $this->startingRule,
            $this->directives
        );
    }

    public function getParserContext(): ParserContext
    {
        if ($this->parserContext === null) {
            throw new RuntimeException(
                'A parser\'s Environment should not be consulted before parsing has started'
            );
        }

        return $this->parserContext;
    }

    private function registerDirective(Directive $directive): void
    {
        $this->directives[$directive->getName()] = $directive;
        foreach ($directive->getAliases() as $alias) {
            $this->directives[$alias] = $directive;
        }
    }

    public function getDocument(): DocumentNode
    {
        if ($this->documentParser === null) {
            throw new RuntimeException('Nothing has been parsed yet.');
        }

        return $this->documentParser->getDocument();
    }

    public function getFilename(): string
    {
        return $this->filename ?: '(unknown)';
    }

    public function parse(ParserContext $parserContext, string $contents): DocumentNode
    {
        $this->parserContext = $parserContext;

        $this->documentParser = new DocumentParserContext($contents, $parserContext, $this);

        if ($this->startingRule->applies($this->documentParser)) {
            $document = $this->startingRule->apply($this->documentParser);
            Assert::isInstanceOf($document, DocumentNode::class);

            return $document;
        }

        throw new InvalidArgumentException('Content is not a valid document content');
    }

    /**
     * @deprecated this should be replaced by proper usage of productions in other productions, by now this is a hack.
     */
    public function parseFragment(DocumentParserContext $documentParserContext, string $contents): DocumentNode
    {
        $documentParserContext = $documentParserContext->withContents($contents);
        if ($this->startingRule->applies($documentParserContext)) {
            $document = $this->startingRule->apply($documentParserContext);
            Assert::isInstanceOf($document, DocumentNode::class);

            return $document;
        }

        throw new InvalidArgumentException('Content is not a valid document content');
    }
}
