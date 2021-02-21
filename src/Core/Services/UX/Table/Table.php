<?php

namespace Coretik\Core\Services\UX\Table;

class Table
{
    protected static $script_loaded = false;
    protected $filters = [];
    protected $columns = [];
    protected $data = [];
    protected $tableClassName = [
        'widefat',
        'fixed'
    ];
    public $with_footer = true;

    public function setFilters(array $filters)
    {
        $this->filters = $filters;
        return $this;
    }

    public function setColumns(array $columns)
    {
        $this->columns = $columns;
        return $this;
    }

    public function setData(array $data)
    {
        $this->data = $data;
        return $this;
    }

    public function addTableClassName(string $classes)
    {
        $this->tableClassName = array_merge($this->tableClassName, [$classes]);
        return $this;
    }

    public function withFooter(bool $with_footer)
    {
        $this->with_footer = $with_footer;
        return $this;
    }

    protected function getRows()
    {
        $rows = [];
        foreach ($this->data as $i => $row) {
            $attr = [];
            if (isset($row['filters'])) {
                foreach ($row['filters'] as $filter_name => $filter_value) {
                    $attr['data-' . $filter_name] = $filter_value;
                }
                unset($row['filters']);
            }
            if ($i % 2 === 0) {
                $attr['class'] = $attr['class'] ?? '' . ' alternate';
            }
            $rows[] = $this->row($attr, ...$row);
        }
        return $rows;
    }

    protected function row($attrs = [])
    {
        $attrs_formatted = [];
        foreach ($attrs as $attr_key => $attr_val) {
            $attrs_formatted[] = $attr_key . '="' . $attr_val . '"';
        }
        $wrapper = '<tr ' . implode(' ', $attrs_formatted) . '>%s</tr>';
        $columns = [];
        for ($i = 1; $i <= func_num_args() - 1; $i++) {
            $columns[] = $this->column(func_get_arg($i), $i - 1);
        }
        return sprintf($wrapper, implode('', $columns));
    }

    protected function column($content, $column_index)
    {
        $wrapper = '<td class="column-%s">%s</td>';
        return sprintf($wrapper, sanitize_title($this->columns[$column_index]), $content);
    }

    protected function table()
    {
        return '<table class="' . implode(' ', $this->tableClassName) . '" cellspacing="0">%s</table>';
    }

    protected function getHeaderColumns()
    {
        $columns = [];
        foreach ($this->columns as $column) :
            $columns[] = sprintf(
                '<th id="column%s" class="manage-column column-%s" scope="col"><b>%s</b></th>',
                sanitize_title($column),
                sanitize_title($column),
                $column,
            );
        endforeach;
        return $columns;
    }

    protected function tableHeader()
    {
        \ob_start();
        ?>
        <thead>
            <tr>
                <?= implode('', $this->getHeaderColumns()) ?>
            </tr>
        </thead>
        <?php
        return \ob_get_clean();
    }

    protected function tableFooter()
    {
        if (!$this->with_footer) {
            return '';
        }
        \ob_start();
        ?>
        <tfoot>
            <tr>
                <?= implode('', $this->getHeaderColumns()) ?>
            </tr>
        </tfoot>
        <?php
        return \ob_get_clean();
    }

    protected function tableBody()
    {
        \ob_start();
        ?>
        <tbody>
            <?= implode('', $this->getRows()); ?>
        </tbody>
        <?php
        return \ob_get_clean();
    }

    protected function filters()
    {
        $filters = [];
        foreach ($this->filters as $filter_name => $values) {
            $filters[] = $this->filtersGroup($filter_name, $values);
        }
        return implode('', $filters);
    }

    protected function filtersGroup($filter_name, $values)
    {
        $wrapper = '<ul class="subsubsub table-filters" data-filter-name="%s">%s</ul>';
        $wrapper_row = '<li><a data-filter="%s" class="%s">%s <span class="count">(%s)</span></a>%s</li>';
        $separator = ' |';
        $rows = [];
        $rows[] = sprintf($wrapper_row, 'all', "current", "Tous", count($this->data), $separator);
        $i = 0;
        $size = count($values);
        foreach ($values as $value => $label) {
            $count = count($this->searchData($filter_name, $value));
            $rows[] = sprintf($wrapper_row, $value, "", $label, $count, $i === $size - 1 ? '' : $separator);
            $i++;
        }
        return sprintf($wrapper, $filter_name, implode('', $rows));
    }

    protected function searchData($filter_name, $filterValue)
    {
        $result = [];
        foreach ($this->data as $data) {
            if (!empty($data['filters']) && !empty($data['filters'][$filter_name]) && $filterValue === $data['filters'][$filter_name]) {
                $result[] = $data;
            }
        }
        return $result;
    }

    public function render($echo = true)
    {
        $wrapper = '<div class="table-container flex flex--direction-column">%s</div>';

        $filters = $this->filters();
        $table = sprintf(
            $this->table(),
            $this->tableHeader() . $this->tableBody() . $this->tableFooter()
        );

        $this->renderScripts();
        return $echo ? printf($wrapper, $filters . $table) : sprintf($wrapper, $filters . $table);
    }

    protected function renderScripts()
    {
        if (!static::$script_loaded) {
            add_action('admin_footer', function () {
                ?>
                <script>
                    (function($) {
                        $('[data-filter]').on('click', function(e){
                            e.preventDefault();
                            $tableContainer = $(this).parents('.table-container');
                            $(this).parents('[data-filter-name]').find('[data-filter]').removeClass('current');
                            $(this).addClass('current');

                            var allFilters = [];
                            $('[data-filter].current').each(function() {
                                if ('all' !== $(this).data('filter')) {
                                    allFilters.push({
                                        name: $(this).parents('[data-filter-name]').data('filter-name'),
                                        value: $(this).data('filter')
                                    });
                                }
                            });

                            $tableBody = $tableContainer.find('tbody').first();
                            $tableBody.find('tr').show();
                            if (allFilters.length > 0) {
                                $.each(allFilters, function (i, data) {
                                    $tableBody.find('> tr:not([data-'+data.name+'~="'+data.value+'"])').hide();
                                });
                            }
                        });
                    })(jQuery);
                </script>
                <?php
            });
            static::$script_loaded = true;
        }
    }
}
