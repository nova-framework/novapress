<?php

namespace Modules\Content\Platform\ContentTypes;

use Modules\Content\Platform\ContentType;
use Modules\Content\Platform\TaxonomyTypeManager;


abstract class Taxonomy extends ContentType
{
    /**
     * @var bool
     */
    protected $unique = false;


    public function __construct(TaxonomyTypeManager $manager, array $options)
    {
        parent::__construct($manager, $options);
    }

    public function isUnique()
    {
        return $this->unique;
    }
}
