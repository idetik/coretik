<?php

namespace Coretik\Core\Models\Traits;

use Coretik\Core\Collection;
use Coretik\Core\Utils\Arr;
use Coretik\Core\Models\Interfaces\ModelInterface;
use Exception;

trait Taxonomy
{
    // For NON HIERARCHICAL only
    public function setTerm(ModelInterface|int|string $term, string $taxonomy): self
    {
        switch (true) {
            case $term instanceof ModelInterface:
                $term = $term->id();
                break;
            default:
                break;
        }

        if (empty($this->tax_input)) {
            $this->tax_input = [];
        }

        $this->tax_input[$taxonomy][] = $term;

        return $this;
    }

    public function terms($taxonomy): Collection
    {
        if (empty($this->{$taxonomy . '_terms'})) {
            $array = Arr::wrap(\wp_get_post_terms($this->id(), $taxonomy));

            if (count($array) < 1) {
                return new Collection([]);
            }

            $this->{$taxonomy . '_terms'} = (new Collection($array))->map(fn ($wp_item) => app()->schema($taxonomy, 'taxonomy')->model($wp_item->term_id, $wp_item));
        }

        return $this->{$taxonomy . '_terms'};
    }

    public function term($taxonomy): ?ModelInterface
    {
        return $this->terms($taxonomy)->first();
    }

    public function detachTerm(ModelInterface|int $term, string $taxonomy)
    {
        switch (true) {
            case $term instanceof ModelInterface:
                $term = $term->id();
                break;
            default:
                break;
        }

        \wp_remove_object_terms($this->id(), $term, $taxonomy);

        if (empty($this->tax_input)) {
            return;
        }

        if (\array_key_exists($taxonomy, $this->tax_input) && \in_array($term, $this->tax_input[$taxonomy])) {
            $this->tax_input[$taxonomy] = \array_filter($this->tax_input[$taxonomy], fn ($modelTerm) => $modelTerm !== $term);
        }

        return $this;
    }
}
