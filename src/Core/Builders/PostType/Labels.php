<?php

namespace Coretik\Core\Builders\PostType;

use Coretik\Core\Collection;
use Coretik\Core\Utils;

/**
 * see @https://developer.wordpress.org/reference/functions/get_post_type_labels/
 */

class Labels extends Collection
{
    protected $isFemininus = false;
    protected $hasApostrophe = false;
    protected $new;

    public function __construct(
        string $singular,
        string $plural,
        string $new = 'nouveau',
        array $labels = []
    ) {
        $this->new = $new;
        $this->set('singular', $singular);
        $this->set('plural', $plural);
        parent::__construct($labels);
    }

    public function isFemininus()
    {
        $this->isFemininus = true;
    }

    public function hasApostrophe()
    {
        $this->hasApostrophe = true;
    }

    public function all()
    {
        $singular = $this->get('singular');
        $plural = $this->get('plural');

        if (!preg_match('/[A-Z]{2,}/', $singular)) {
            $singular_low = mb_strtolower($singular);
        } else {
            $singular_low = $singular;
        }

        if (!preg_match('/[A-Z]{2,}/', $plural)) {
            $plural_low = mb_strtolower($plural);
        } else {
            $plural_low = $plural;
        }

        if ($this->isFemininus) {
            $un = "une";
            $le = $this->hasApostrophe ? "l'" : "la ";
            $du = $this->hasApostrophe ? "de l'" : "de la ";
            $du_nouveau = "de la " . $this->new;
            $tous = "Toutes";
            $nouveau_ucfirst = mb_convert_case($this->new, MB_CASE_TITLE, "UTF-8");
            $derniers_ucfirst = "Dernières";
            $mis = "mise";
            $reconverti = "reconvertie";
            $enregistre = "enregistrée";
            $planifie = "planifiée";
            $utilises = "utilisées";
            $aucun = "Aucune";
            $parent = "parente";
        } else {
            $un = "un";
            $le = $this->hasApostrophe ? "l'" : "le ";
            $du = $this->hasApostrophe ? "de l'" : "du ";
            $du_nouveau = "du " . $this->new;
            $tous = "Tous";
            $nouveau_ucfirst = mb_convert_case($this->new, MB_CASE_TITLE, "UTF-8");
            $derniers_ucfirst = "Derniers";
            $mis = "mis";
            $reconverti = "reconverti";
            $enregistre = "enregistré";
            $planifie = "planifié";
            $utilises = "utilisés";
            $aucun = "Aucun";
            $parent = "parent";
        }

        $labels = [
            'extended_custom_labels'     => true,
            'singular'                   => $singular,
            'singular_low'               => $singular_low,
            'plural'                     => $plural,
            'plural_low'                 => $plural_low,
            'add_new'                    => 'Ajouter',
            'add_new_item'               => sprintf('Ajouter %s %s', $un, $singular),
            'add_or_remove_items'        => sprintf('Ajouter ou supprimer des %s', $plural_low),
            'all_items'                  => sprintf('%s les %s', $tous, $plural),
            'back_to_items'              => sprintf('&larr; Revenir aux %s', $plural),
            'choose_from_most_used'      => sprintf('Choisissez parmi les %s les plus %s', $plural_low, $utilises),
            'archives'                   => sprintf('%s %s', $derniers_ucfirst, $plural),
            'attributes'                 => sprintf('Attributs %s%s', $du, $singular),
            'edit_item'                  => sprintf('Modifier %s%s', $le, $singular),
            'filter_by'                  => sprintf('Filtrer par %s', $singular_low),
            'filter_items_list'          => sprintf('Filtrer la liste des %s', $plural_low),
            'insert_into_item'           => 'Insérer',
            'item_published'             => sprintf('%s %s en ligne.', $singular, $mis),
            'item_published_privately'   => sprintf('%s %s en ligne en privé.', $singular, $mis),
            'item_saved'                 => sprintf('%s %s.', $singular, $enregistre),
            'item_reverted_to_draft'     => sprintf('%s %s en brouillon.', $singular, $reconverti),
            'item_scheduled'             => sprintf('%s %s.', $singular, $planifie),
            'item_updated'               => sprintf('%s %s à jour.', $singular, $mis),
            'items_list'                 => sprintf('Liste des %s', $plural),
            'items_list_navigation'      => sprintf('Navigation de la liste des %s', $plural),
            'menu_name'                  => $plural,
            'most_used'                  => sprintf('Les plus %s', $utilises),
            'name'                       => $plural,
            'name_admin_bar'             => $singular,
            'new_item'                   => sprintf('%s %s', $nouveau_ucfirst, $singular),
            'new_item_name'              => sprintf('Nom %s %s', $du_nouveau, $singular),
            'no_item'                    => sprintf('%s %s', $aucun, $singular_low),
            'no_terms'                   => sprintf('%s %s', $aucun, $singular_low),
            'not_found'                  => 'Aucun résultat.',
            'not_found_in_trash'         => 'Aucun résultat dans la corbeille.',
            'parent_item'                => sprintf('%s %s', $singular, $parent),
            'parent_item_colon'          => sprintf('%s %s :', $singular, $parent),
            'popular_items'              => sprintf('%s populaires', $plural),
            'separate_items_with_commas' => sprintf('Séparez les %s par des virgules', $plural_low),
            'search_items'               => sprintf('Rechercher des %s', $plural),
            'singular_name'              => $singular,
            'update_item'                => sprintf('Mettre à jour %s%s', $le, $singular),
            'uploaded_to_this_item'      => 'Fichiers attachés',
            'view_item'                  => sprintf('Voir %s%s', $le, $singular),
            'view_items'                 => sprintf('Voir les %s', $plural),
        ];

        return array_merge($labels, parent::all());
    }
}
