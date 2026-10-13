<?php

namespace Coretik\Services\SchemaViewer\Relations;

use Coretik\Core\Builders\Interfaces\BuilderInterface;
use Coretik\Core\Builders\Interfaces\ModelableInterface;
use Coretik\Core\Models\Handlers\Relationship\BelongsToRelationInterface;
use Coretik\Core\Schema;

/**
 * Relations between the schema objects, discovered without loading data:
 * - taxonomies attached to post types
 * - hierarchical post types and taxonomies
 * - BelongsToHandler and ShadowTaxonomyHandler handlers (BelongsToRelationInterface)
 * - belongsTo() / hasMany() / hasOne() calls in the models methods
 */
class RelationsMap
{
    const TYPES = ['post', 'taxonomy', 'user', 'comment'];

    /** @var array<string, BuilderInterface> Builders by node id */
    protected array $builders = [];

    /** @var array<class-string, string> Node id by model class */
    protected array $models = [];

    public function __construct(protected Schema $schema)
    {
        foreach (static::TYPES as $type) {
            foreach ($this->schema->type($type)->all() as $builder) {
                $this->builders[$this->nodeId($builder)] = $builder;
            }
        }

        foreach ($this->builders as $node => $builder) {
            $class = $this->modelClass($builder);
            if (null !== $class) {
                $this->models[$class] = $node;
            }
        }
    }

    /**
     * @return Relation[] Unique relations: "A hasMany B" and "B belongsTo A" are the same relation
     */
    public function relations(): array
    {
        $relations = [];
        foreach ([...$this->declared(), ...$this->fromModels()] as $relation) {
            $relations[$relation->key()][] = $relation;
        }

        return \array_map(fn (array $same) => $this->merge($same), \array_values($relations));
    }

    /**
     * Mermaid entity relationship diagram
     */
    public function mermaid(): string
    {
        $lines = ['erDiagram'];
        foreach ($this->relations() as $relation) {
            $lines[] = \sprintf(
                '    %s %s %s : "%s"',
                $this->mermaidId($relation->from),
                $this->cardinality($relation->type),
                $this->mermaidId($relation->to),
                \str_replace('"', "'", $relation->label())
            );
        }
        return \implode("\n", $lines);
    }

    /**
     * Relations declared in the schema: taxonomies, hierarchies and belongs to handlers
     *
     * @return Relation[]
     */
    protected function declared(): array
    {
        $relations = [];

        foreach ($this->builders as $node => $builder) {
            if (\method_exists($builder, 'getObjectTypes')) {
                foreach ($builder->getObjectTypes() as $postType) {
                    $from = $this->node(\is_string($postType) ? $postType : $postType->getName(), 'post');
                    if (null !== $from) {
                        $relations[] = new Relation($from, $node, Relation::TAXONOMY);
                    }
                }
            }

            if (\method_exists($builder, 'args') && $builder->args()->get('hierarchical')) {
                $relations[] = new Relation($node, $node, Relation::PARENT);
            }

            foreach ($this->belongsToHandlers($builder) as $handler) {
                $target = $handler->belongsToBuilder();
                if (null !== $target) {
                    $relations[] = new Relation($node, $this->nodeId($target), Relation::BELONGS_TO, $this->pivot($handler));
                }
            }
        }

        return $relations;
    }

    /**
     * belongsTo() / hasMany() / hasOne() calls found in the models methods
     *
     * @return Relation[]
     */
    protected function fromModels(): array
    {
        $relations = [];

        foreach ($this->models as $class => $node) {
            $found = [];
            foreach ((new \ReflectionClass($class))->getMethods() as $method) {
                // coretik internals (Relationships trait…) are not project relations
                if (\str_starts_with($method->getDeclaringClass()->getName(), 'Coretik\\Core\\')) {
                    continue;
                }

                foreach ($this->relationCalls($method) as [$type, $target]) {
                    $to = $this->resolveTarget($target, $method->getDeclaringClass());
                    if (null !== $to) {
                        $found[] = [$type, $to];
                    }
                }
            }

            // A belongs to handler without target gives the pivot of the only belongsTo() of the model
            $belongsTo = \array_filter($found, fn ($relation) => Relation::BELONGS_TO === $relation[0]);
            $pivot = 1 === \count($belongsTo) ? $this->unresolvedPivot($node) : '';

            foreach ($found as [$type, $to]) {
                $relations[] = new Relation($node, $to, $type, Relation::BELONGS_TO === $type ? $pivot : '');
            }
        }

        return $relations;
    }

    /**
     * @return array<int, array{0: string, 1: string}> [relation type, target class or builder name]
     */
    protected function relationCalls(\ReflectionMethod $method): array
    {
        $file = $method->getFileName();
        if (false === $file || !\is_readable($file)) {
            return [];
        }

        $lines = \file($file);
        $code = \implode('', \array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));

        \preg_match_all('/->\s*(belongsTo|hasMany|hasOne)\(\s*(?:([\\\\\w]+)::class|[\'"]([\w-]+)[\'"])/', $code, $matches, PREG_SET_ORDER);

        return \array_map(fn ($match) => [$match[1], '' !== $match[2] ? $match[2] : $match[3]], $matches);
    }

    /**
     * Node of a relation target: a model class (short name resolved like PHP does) or a builder name
     */
    protected function resolveTarget(string $target, \ReflectionClass $context): ?string
    {
        foreach ($this->candidateClasses($target, $context) as $class) {
            if (isset($this->models[$class])) {
                return $this->models[$class];
            }
        }

        return $this->node($target);
    }

    /**
     * @return string[]
     */
    protected function candidateClasses(string $target, \ReflectionClass $context): array
    {
        $target = \ltrim($target, '\\');
        $candidates = [$target, $context->getNamespaceName() . '\\' . $target];

        // use statements of the file
        $file = $context->getFileName();
        if (false !== $file && \is_readable($file)) {
            $first = \explode('\\', $target)[0];
            \preg_match_all('/^use\s+([\\\\\w]+?)(?:\s+as\s+(\w+))?\s*;/m', \file_get_contents($file), $uses, PREG_SET_ORDER);
            foreach ($uses as $use) {
                $alias = $use[2] ?? '';
                $alias = '' !== $alias ? $alias : \substr(\strrchr('\\' . $use[1], '\\'), 1);
                if ($alias === $first) {
                    $candidates[] = $use[1] . \substr($target, \strlen($first));
                }
            }
        }

        return $candidates;
    }

    /**
     * Pivot of a belongs to handler without target: the model belongsTo() call gives the target
     */
    protected function unresolvedPivot(string $node): string
    {
        $pivots = [];
        foreach ($this->belongsToHandlers($this->builders[$node]) as $handler) {
            if (null === $handler->belongsToBuilder()) {
                $pivots[] = $this->pivot($handler);
            }
        }
        return 1 === \count($pivots) ? $pivots[0] : '';
    }

    /**
     * @return BelongsToRelationInterface[]
     */
    protected function belongsToHandlers(BuilderInterface $builder): array
    {
        if (!\method_exists($builder, 'getHandlers')) {
            return [];
        }

        $handlers = [];
        foreach ($builder->getHandlers() as $handler) {
            if ($handler instanceof BelongsToRelationInterface) {
                $handlers[] = $handler;
            }
        }
        return $handlers;
    }

    protected function pivot(BelongsToRelationInterface $handler): string
    {
        return \is_string($handler->pivot()) ? $handler->pivot() : '';
    }

    /**
     * @param Relation[] $same The same relation, from both sides
     */
    protected function merge(array $same): Relation
    {
        $types = \array_unique(\array_map(fn (Relation $relation) => $relation->type, $same));
        $type = match (true) {
            \in_array(Relation::HAS_MANY, $types, true) => Relation::HAS_MANY,
            \in_array(Relation::HAS_ONE, $types, true) => Relation::HAS_ONE,
            default => $same[0]->type,
        };

        // Oriented as the "one" side for hasMany / hasOne
        $reference = \current(\array_filter($same, fn (Relation $relation) => $relation->type === $type));
        $details = \array_values(\array_unique(\array_filter(\array_map(fn (Relation $relation) => $relation->detail, $same))));

        return new Relation($reference->from, $reference->to, $type, \implode(', ', $details));
    }

    protected function cardinality(string $type): string
    {
        return match ($type) {
            Relation::HAS_MANY => '||--o{',
            Relation::HAS_ONE => '||--o|',
            Relation::BELONGS_TO => '}o--||',
            Relation::TAXONOMY => '}o--o{',
            Relation::PARENT => '|o--o{',
            default => '}o--o{',
        };
    }

    protected function modelClass(BuilderInterface $builder): ?string
    {
        if (!$builder instanceof ModelableInterface || !$builder->hasFactory()) {
            return null;
        }

        try {
            return \get_class($builder->model());
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function nodeId(BuilderInterface $builder): string
    {
        return $builder->getType() . ':' . $builder->getName();
    }

    /**
     * Node id of a builder name, in the given type or any type
     */
    protected function node(string $name, ?string $type = null): ?string
    {
        foreach ($type ? [$type] : static::TYPES as $candidate) {
            if (isset($this->builders[$candidate . ':' . $name])) {
                return $candidate . ':' . $name;
            }
        }
        return null;
    }

    /**
     * Mermaid entity name: the builder name, prefixed by its type when another type uses the same name
     */
    protected function mermaidId(string $node): string
    {
        [$type, $name] = \explode(':', $node, 2);
        $sameName = \array_filter(\array_keys($this->builders), fn ($other) => \str_ends_with($other, ':' . $name));
        $id = \count($sameName) > 1 ? $type . '_' . $name : $name;
        return \preg_replace('/[^\w-]/', '_', $id);
    }
}
