<?php

namespace Modules\Content\Controllers\Admin;

use Nova\Database\ORM\ModelNotFoundException;
use Nova\Http\Request;
use Nova\Support\Facades\Cache;
use Nova\Support\Facades\Redirect;
use Nova\Support\Facades\Response;
use Nova\Support\Facades\Validator;
use Nova\Support\Arr;

use Modules\Content\Models\Menu;
use Modules\Content\Models\Taxonomy;
use Modules\Content\Models\Term;
use Modules\Content\Support\Facades\TaxonomyType;
use Modules\Platform\Controllers\Admin\BaseController;


class Menus extends BaseController
{

    protected function validator(array $data, $id = 0)
    {
        $rules = array(
            'name'        => 'required|valid_text',
            'slug'        => 'min:4|max:100|alpha_dash|unique_slug:' .intval($id),
            'description' => 'required|valid_text',
        );

        $messages = array(
            'unique_slug' => __d('content', 'The :attribute field is not an unique Menu slug.'),
            'valid_text'  => __d('content', 'The :attribute field is not a valid text.'),
        );

        $attributes = array(
            'name'        => __d('content', 'Name'),
            'slug'        => __d('content', 'Slug'),
            'description' => __d('content', 'Description'),
        );

        // Add the custom Validation Rule commands.
        Validator::extend('unique_slug', function ($attribute, $value, $parameters)
        {
            $id = array_shift($parameters);

            $query = Taxonomy::where('taxonomy', 'nav_menu')->whereHas('term', function ($query) use ($value)
            {
                $query->where('name', $value);

            })->where('id', '<>', (int) $id);

            return ! $query->exists();
        });

        Validator::extend('valid_text', function ($attribute, $value, $parameters)
        {
            return ($value == strip_tags($value));
        });

        return Validator::make($data, $rules, $messages, $attributes);
    }

    public function index()
    {
        $taxonomyType = TaxonomyType::make('nav_menu');

        //
        $menus = Menu::paginate(15);

        return $this->createView()
            ->shares('title', $taxonomyType->label('title'))
            ->with('menus', $menus);
    }

    public function store(Request $request)
    {
        $input = $request->all();

        // Validate the Input data.
        $validator = $this->validator($input);

        if ($validator->fails()) {
            return Redirect::back()->withErrors($validator->errors());
        }

        $name = Arr::get($input, 'name');

        if (empty($slug = Arr::get($input, 'slug'))) {
            $slug = Term::uniqueSlug($name, 'nav_menu');
        }

        $term = Term::create(array(
            'name'   => $name,
            'slug'   => $slug,
            'group'  => 0,
        ));

        $taxonomy = Taxonomy::create(array(
            'term_id'     => $term->id,
            'taxonomy'    => 'nav_menu',
            'description' => Arr::get($input, 'description'),
            'parent_id'   => 0,
            'count'       => 0,
        ));

        return Redirect::back()
            ->with('success', __d('content', 'The Menu <b>{0}</b> was successfully created.', $name));
    }

    public function update(Request $request, $id)
    {
        $input = $request->all();

        try {
            $menu = Menu::with('term')->findOrFail($id);
        }
        catch (ModelNotFoundException $e) {
            return Redirect::back()->with('danger', __d('content', 'Menu not found: #{0}', $id));
        }

        $term = $menu->term;

        // Validate the Input data.
        $validator = $this->validator($input, $menu->id);

        if ($validator->fails()) {
            return Redirect::back()->withErrors($validator->errors());
        }

        $name = Arr::get($input, 'name');

        if (empty($slug = Arr::get($input, 'slug'))) {
            $slug = Term::uniqueSlug($name, 'nav_menu', $menu->id);
        }

        // Get the original information of the Term.
        $lastName = $term->name;
        $lastSlug = $term->slug;

        // Update the Term.
        $term->name = $name;
        $term->slug = $slug;

        $term->save();

        // Update the Taxonomy.
        $menu->description = Arr::get($input, 'description');

        $menu->save();

        // Invalidate the cached menu data.
        Cache::forget('content.menus.' .$lastSlug);

        return Redirect::back()
            ->with('success', __d('content', 'The Menu <b>{0}</b> was successfully updated.', $lastName));
    }

    public function destroy($id)
    {
        try {
            $menu = Menu::with('term', 'items')->findOrFail($id);
        }
        catch (ModelNotFoundException $e) {
            return Redirect::back()->with('danger', __d('content', 'Menu not found: #{0}', $id));
        }

        $name = $menu->name;
        $slug = $menu->slug;

        //
        // Delete the Menu together with its items and Term.

        $menu->items->each(function ($item) use ($menu)
        {
            $item->taxonomies()->detach($menu);

            $item->delete();
        });

        $menu->term->delete();

        $menu->delete();

        // Invalidate the cached menu data.
        Cache::forget('content.menus.' .$slug);

        return Redirect::back()
            ->with('success', __d('content', 'The Menu {0} was successfully deleted.', $name));
    }
}
