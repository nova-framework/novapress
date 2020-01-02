<?php

namespace Modules\Content\Controllers\Admin\Editor;

use Nova\Database\ORM\ModelNotFoundException;
use Nova\Http\Request;
use Nova\Routing\Controller as BaseController;
use Nova\Support\Facades\Response;

use Modules\Content\Models\Post;
use Modules\Content\Models\Taxonomy;
use Modules\Content\Models\Term;


class Tags extends BaseController
{

    public function lists(Request $request, $postId)
    {
        try {
            $post = Post::with('taxonomies')->findOrFail($postId);
        }
        catch (ModelNotFoundException $e) {
            return Response::json(array('error' => __d('content', 'Record not found: #{0}', $id)), 400);
        }

        $taxonomies = $post->taxonomies->where('taxonomy', 'post_tag');

        $results = $taxonomies->map(function ($taxonomy)
        {
            return array(
                'id'   => $taxonomy->id,
                'name' => $taxonomy->name
            );

        })->toArray();

        return Response::json($results, 200);
    }

    public function attach(Request $request, $postId)
    {
        try {
            $post = Post::with('taxonomies')->findOrFail($postId);
        }
        catch (ModelNotFoundException $e) {
            return Response::json(array('error' => __d('content', 'Record not found: #{0}', $postId)), 400);
        }

        $taxonomies = $post->taxonomies->where('taxonomy', 'post_tag');

        if (empty($value = $request->input('tags'))) {
            return Response::json(array('error' => __d('content', 'The Tags value is required')), 400);
        }

        $names = array_filter(array_map(function ($name)
        {
            return trim(preg_replace('/[\s]+/mu', ' ', $name));

        }, explode(',', $value)));

        $results = array_filter(array_map(function ($name) use ($post, $taxonomies)
        {
            $taxonomy = $taxonomies->where('name', $name)->first();

            if (is_null($taxonomy)) {
                $taxonomy = $this->resolveTaxonomyByName($name);

                $post->taxonomies()->attach($taxonomy);

                $taxonomy->updateCount();

                return array(
                    'id'   => $taxonomy->id,
                    'name' => $taxonomy->name
                );
            }

        }, $names));

        return Response::json($results, 200);
    }

    protected function resolveTaxonomyByName($name)
    {
        return Taxonomy::with('term')->where('taxonomy', 'post_tag')->whereHas('term', function ($query) use ($name)
        {
            $query->where('name', $name);

        })->firstOr(function () use ($name)
        {
            $slug = Term::uniqueSlug($name, 'post_tag');

            $term = Term::create(array(
                'name'   => $name,
                'slug'   => $slug,
            ));

            $taxonomy = Taxonomy::create(array(
                'term_id'     => $term->id,
                'taxonomy'    => 'post_tag',
                'description' => '',
            ));

            $taxonomy->load('term');

            return $taxonomy;
        });
    }

    public function detach(Request $request, $postId, $tagId)
    {
        try {
            $post = Post::with('taxonomies')->findOrFail($postId);
        }
        catch (ModelNotFoundException $e) {
            return Response::json(array('error' => __d('content', 'Record not found: #{0}', $id)), 400);
        }

        $taxonomy = $post->taxonomies->where('taxonomy', 'post_tag')->find($tagId);

        if (is_null($taxonomy)) {
            return Response::json(array('error' => __d('content', 'Taxonomy not found: #{0}', $tagId)), 400);
        }

        $post->taxonomies()->detach($tagId);

        // Update the count field in the taxonomy.
        $taxonomy->updateCount();

        return Response::json(array('success' => true), 200);
    }
}
