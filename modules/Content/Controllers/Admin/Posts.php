<?php

namespace Modules\Content\Controllers\Admin;

use Nova\Database\ORM\ModelNotFoundException;
use Nova\Http\Request;
use Nova\Support\Facades\Auth;
use Nova\Support\Facades\Cache;
use Nova\Support\Facades\Config;
use Nova\Support\Facades\Event;
use Nova\Support\Facades\Hash;
use Nova\Support\Facades\Redirect;
use Nova\Support\Facades\Response;
use Nova\Support\Facades\URL;
use Nova\Support\Facades\Session;
use Nova\Support\Arr;
use Nova\Support\Str;

use Modules\Content\Models\Menu;
use Modules\Content\Models\MenuItem;
use Modules\Content\Models\Post;
use Modules\Content\Models\Tag;
use Modules\Content\Models\Taxonomy;
use Modules\Content\Models\Term;
use Modules\Content\Support\Facades\PostType;
use Modules\Content\Support\Facades\TaxonomyType;
use Modules\Platform\Controllers\Admin\BaseController;
use Modules\Users\Models\User;


class Posts extends BaseController
{

    public function taxonomy(Request $request, $type, $slug)
    {
        $taxonomyType = TaxonomyType::make($type);

        //
        $name = $taxonomyType->label('name');

        $statuses = Config::get('content::statuses', array());

        // Get the Taxonomy instance.
        $taxonomy = Taxonomy::where('taxonomy', $type)->slug($slug)->first();

        //
        $title = __d('content', 'Posts in the {0} : {1}', $name, $taxonomy->name);
        $name  = __d('content', 'Post');

        // Get the records.
        $posts = $taxonomy->posts()
            ->where('type', 'post')
            ->newest()
            ->paginate(15);

        return $this->createView(compact('type', 'name', 'statuses', 'posts'), 'Index')
            ->shares('title', $title);
    }

    public function index(Request $request, $slug)
    {
        $postType = PostType::findBySlug($slug);

        $type = $postType->name();

        //
        $name = $postType->label('name');

        $statuses = Config::get('content::statuses', array());

        // Get the records.
        $posts = Post::with('author', 'taxonomies')
            ->type($type)
            ->newest()
            ->paginate(15);

        return $this->createView()
            ->shares('title', $postType->label('title'))
            ->with(compact('type', 'name', 'statuses', 'posts', 'postType'));
    }

    public function create(Request $request, $type)
    {
        $authUser = Auth::user();

        $postType = PostType::make($type);

        $name = $postType->label('name');

        //
        $status     = 'draft';
        $visibility = 'public';

        // Create a new Post instance.
        $post = Post::create(array(
            'type'           => $type,
            'status'         => 'draft',
            'author_id'      => $userId = $authUser->id,
            'menu_order'     => 0,
            'comment_status' => ($type == 'post') ? 'open' : 'closed',
        ));

        $post->name = $post->id;

        // Save the Post again, to update its name.
        $post->save();

        // Handle the Metadata.
        $editLock = sprintf('%d:%d', time(), $userId);

        $post->saveMeta('edit_lock', $editLock);

        if ($type === 'block') {
            $post->saveMeta(array(
                'block_handler_class' => null,
                'block_handler_param' => null,
            ));
        }

        $post->name = '';

        //
        $menuSelect = $this->generateParentSelect();

        $blockTitle = false;
        $blockMode  = 'show';
        $blockPath  = null;

        $categories = $this->generateCategories(
            $ids = $post->taxonomies()->where('taxonomy', 'category')->lists('id')
        );

        $categorySelect = $this->generateCategorySelect();

        $tags = '';

        // Revisions.
        $revisions = $post->newCollection();

        // The last editor.
        $lastEditor = $authUser;

        // Compute the stylesheets needed to be loaded in editor.
        $stylesheets = $this->getDefaultThemeStylesheets();

        //
        $data = compact('post', 'postType', 'status', 'visibility', 'type', 'name', 'categories', 'revisions');

        return $this->createView($data, 'Edit')
            ->shares('title', $postType->label('addNewItem'))
            ->with(compact('categorySelect', 'menuSelect', 'lastEditor', 'tags', 'stylesheets'))
            ->with('creating', true);
    }

    public function edit(Request $request, $id)
    {
        $authUser = Auth::user();

        try {
            $post = Post::with('thumbnail', 'revisions')->findOrFail($id);
        }
        catch (ModelNotFoundException $e) {
            return Redirect::back()->with('danger', __d('content', 'Record not found: #{0}', $id));
        }

        $type = $post->type;

        $postType = PostType::make($type);

        // Handle the Metadata.
        $editLock = sprintf('%d:%d', time(), $authUser->id);

        $post->saveMeta('edit_lock', $editLock);

        // Save the Post, to update its metadata.
        $post->save();

        //
        $name = $postType->label('name');

        $status = $post->status;

        if (Str::contains($status, '-')) {
            // The status could be: private-draft and private-review
            list ($visibility, $status) = explode('-', $status, 2);
        }

        // We should compute every field.
        else if ($status == 'password') {
            $status     = 'published';
            $visibility = 'password';
        } else if ($status == 'private') {
            $status     = 'published';
            $visibility = 'private';
        } else {
            $visibility = 'public';
        }

        //
        $categories = $this->generateCategories(
            $ids = $post->taxonomies()->where('taxonomy', 'category')->lists('id')
        );

        $categorySelect = $this->generateCategorySelect($ids);

        // The Tags.
        $tags = $post->taxonomies()->where('taxonomy', 'post_tag')->get();

        $tags = $tags->map(function ($tag)
        {
            return '<div class="tag-item"><a class="delete-tag-link" href="#" data-name="' .$tag->name  .'" data-id="' .$tag->id  .'"><i class="fa fa-times-circle"></i></a> ' .$tag->name .'</div>';

        })->implode("\n");

        // No menu selection on edit mode.
        $menuSelect = '';

        // Revisions.
        $revisions = $post->revisions()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // The last editor.
        $lastEditor = isset($post->meta->edit_last)
            ? User::findOrFail($post->meta->edit_last)
            : $authUser;

        // Compute the stylesheets needed to be loaded in editor.
        $stylesheets = $this->getDefaultThemeStylesheets();

        //
        $data = compact('post', 'postType', 'status', 'visibility', 'type', 'name', 'categories', 'revisions');

        return $this->createView($data, 'Edit')
            ->shares('title', $postType->label('editItem'))
            ->with(compact('categorySelect', 'menuSelect', 'lastEditor', 'tags', 'stylesheets'))
            ->with('creating', false);
    }

    public function update(Request $request, $id)
    {
        $authUser = Auth::user();

        //
        $input = $request->all();

        try {
            $post = Post::findOrFail($id);
        }
        catch (ModelNotFoundException $e) {
            Session::flash('danger', __d('content', 'Record not found: #{0}', $id));

            return Response::json(array('redirectTo' => 'refresh'), 400);
        }

        $postType = PostType::make($type = $post->type);

        $creating = (bool) Arr::get($input, 'creating', 0);

        // Fire the starting event.
        Event::dispatch('content.post.updating', array($post, $creating));

        //
        // Create a new Revision from the current Post instance.

        if (! $creating) {
            $this->createRevision($post, $authUser);
        }

        //
        // Update the current Post instance.

        $post->title = $title = Arr::get($input, 'title');

        $post->content = Arr::get($input, 'content');

        if (empty($slug = Arr::get($input, 'slug'))) {
            $slug = Post::uniqueName($title, ! $creating ? $post->id : null);
        }

        $post->name = $slug;

        $post->guid = site_url($slug);

        // The Status.
        $status = Arr::get($input, 'status', 'draft');

        if ($creating && ($status === 'draft')) {
            $status = 'publish';
        }

        $visibility = Arr::get($input, 'visibility', 'public');

        $password = null;

        if ($visibility == 'private') {
            // The status could be: private, private-draft and private-review
            $status = ($status == 'publish') ? 'private' : 'private-' .$status;
        }

        // Only the published posts can have a password.
        else if (($visibility == 'password') && ($status == 'publish')) {
            $status = 'password';

            $password = Hash::make($input['password']);
        }

        $post->status   = $status;
        $post->password = $password;

        if ($type == 'page') {
            $post->parent_id  = (int) Arr::get($input, 'parent', 0);
            $post->menu_order = (int) Arr::get($input, 'order',  0);
        }

        // For the Blocks.
        else if ($type == 'block') {
            $post->menu_order = (int) Arr::get($input, 'order',  0);
        }

        // Save the Post instance before to continue the processing.
        $post->save();

        // Handle the MetaData.
        $post->saveMeta(array(
            'thumbnail_id' => (int) Arr::get($input, 'thumbnail'),

            'edit_last' => $authUser->id,
        ));

        if ($type == 'block') {
            $post->saveMeta(array(
                'block_show_title' => (int) Arr::get($input, 'block-show-title'),

                'block_visibility_mode'   => Arr::get($input, 'block-show-mode'),
                'block_visibility_path'   => Arr::get($input, 'block-show-path'),

                'block_visibility_filter' => Arr::get($input, 'block-show-filter', 'any'),
                'block_widget_position'   => Arr::get($input, 'block-position'),
            ));
        }

        // We have a standard Post.
        else if ($type == 'post') {
            $categories = array();

            if (! empty($result = Arr::get($input, 'categories'))) {
                // The value is something like: 'category[]=1&category[]=3&category[]=4'

                $categories = array_map(function ($item)
                {
                    list (, $value) = explode('=', $item);

                    return (int) $value;

                }, explode('&', urldecode($result)));
            }

            $taxonomies = $post->taxonomies()
                ->where('taxonomy', 'category')
                ->lists('id');

            if (! empty($ids = array_diff($taxonomies, $categories))) {
                $post->taxonomies()->detach($ids);
            }

            if (! empty($ids = array_diff($categories, $taxonomies))) {
                $post->taxonomies()->attach($ids);
            }

            // Update the count field in the affected taxonomies.
            $ids = array_unique(array_merge($taxonomies, $categories));

            $taxonomies = Taxonomy::whereIn('id', $ids)->get();

            $taxonomies->each(function ($taxonomy)
            {
                $taxonomy->updateCount();
            });
        }

        // Fire the finishing event.
        Event::dispatch('content.post.updated', array($post, $creating));

        // Update the edit lock.
        $post->saveMeta('edit_lock', null);

        // Invalidate the content caches.
        Cache::section('content')->flush();

        //
        Session::flash(
            'success', __d('content', 'The {0} <b>#{1}</b> was successfully saved.', $postType->label('name'), $post->id)
        );

        return Response::json(array(
            'redirectTo' => site_url('admin/content/' .Str::plural($type))

        ), 200);
    }

    protected function createRevision(Post $post, User $user)
    {
        $version = 0;

        $names = $post->revisions()->lists('name');

        foreach ($names as $name) {
            if (preg_match('#^(?:\d+)-revision-v(\d+)$#', $name, $matches) === 1) {
                $version = max($version, (int) $matches[1]);
            }
        }

        $version++;

        // Save the current version also on the Post metadata.
        $post->saveMeta('version', $version);

        // Compute the slug of current Revision.
        $slug = sprintf('%d-revision-v%d', $post->id, $version);

        $revision = Post::create(array(
            'content'        => $post->content,
            'title'          => $post->title,
            'excerpt'        => $post->excerpt,
            'status'         => 'inherit',
            'password'       => $post->password,
            'name'           => $slug,
            'parent_id'      => $post->id,
            'guid'           => site_url($slug),
            'menu_order'     => $post->menu_order,
            'type'           => 'revision',
            'mime_type'      => $post->mime_type,
            'author_id'      => $user->id,
            'comment_status' => 'closed',
        ));

        $revision->saveMeta('version', $version);
    }

    public function destroy($id)
    {
        try {
            $post = Post::with('revisions', 'taxonomies')->findOrFail($id);
        }
        catch (ModelNotFoundException $e) {
            return Redirect::back()->with('danger', __d('content', 'Record not found: #{0}', $id));
        }

        $postType = PostType::make($post->type);

        // Fire the starting event.
        Event::dispatch('content.post.deleting', array($post));

        // Delete first the Post Revisions.
        $post->revisions->each(function ($revision)
        {
            $revision->delete();
        });

        // Detach the associated Taxonomies.
        $post->taxonomies()->detach();

        $post->taxonomies->each(function ($taxonomy)
        {
            $taxonomy->updateCount();
        });

        // Finally, delete the Post.
        $post->delete();

        // Fire the finishing event.
        Event::dispatch('content.post.deleted', array($post));

        // Invalidate the content caches.
        Cache::section('content')->flush();

        return Redirect::back()
            ->with('success', __d('content', 'The {0} <b>#{1}</b> was successfully deleted.', $postType->label('name'), $post->id));
    }

    protected function generateCategories(array $categories = array(), $taxonomies = null, $level = 0)
    {
        $result = '';

        if (is_null($taxonomies)) {
            $taxonomies = Taxonomy::with('children')->where('taxonomy', 'category')->where('parent_id', 0)->get();
        }

        foreach ($taxonomies as $taxonomy) {
            $result .= '<div class="checkbox" style="padding-left: ' .(($level > 0) ? ($level * 25) .'px' : '') .'"><label><input class="category-checkbox" name="category[]" value="' .$taxonomy->id .'" type="checkbox" ' .(in_array($taxonomy->id, $categories) ? ' checked="checked"' : '') .'> ' .$taxonomy->name .'</label></div>';

            // Process the children.
            $taxonomy->load('children');

            if (! $taxonomy->children->isEmpty()) {
                $level++;

                $result .= $this->generateCategories($categories, $taxonomy->children, $level);
            }
        }

        return $result;
    }

    protected function generateCategorySelect(array $categories = array(), $taxonomies = null, $level = 0)
    {
        $result = '';

        if (is_null($taxonomies)) {
            $taxonomies = Taxonomy::with('children')->where('taxonomy', 'category')->where('parent_id', 0)->get();
        }

        foreach ($taxonomies as $taxonomy) {
            $result .= '<option value="' .$taxonomy->id .'"' .(in_array($taxonomy->id, $categories) ? ' selected="selected"' : '') .'>' .trim(str_repeat('--', $level) .' ' .$taxonomy->name) .'</option>' ."\n";

            // Process the children.
            $taxonomy->load('children');

            if (! $taxonomy->children->isEmpty()) {
                $level++;

                $result .= $this->generateCategorySelect($categories, $taxonomy->children, $level);
            }
        }

        return $result;
    }

    protected function generateParentSelect($menu = 'nav_menu', $parentId = 0, $items = null, $level = 0)
    {
        $result = '';

        if (is_null($items)) {
            $items = Post::where('type', 'page')
                ->whereIn('status', array('publish', 'password'))
                ->where('parent_id', 0)
                ->get();

            //
            $result = '<option value="0"' .(($parentId == 0) ? ' selected="selected"' : '') .'>' .__d('content', '(no parent)') .'</option>';
        }

        foreach ($items as $item) {
            $result .= '<option value="' .$item->id .'"' .(($item->id == $parentId) ? ' selected="selected"' : '') .'>' .trim(str_repeat('--', $level) .' ' .$item->title) .'</option>' ."\n";

            // Process the children.
            $children = $item->children()
                ->where('type', 'page')
                ->whereIn('status', array('publish', 'password'))
                ->get();

            if (! $children->isEmpty()) {
                $level++;

                $result .= $this->generateParentSelect($menu, $parentId, $children, $level);
            }
        }

        return $result;
    }

    protected function getDefaultThemeStylesheets()
    {
        $stylesheets = array();

        $theme = Config::get('app.theme');

        //
        $event = sprintf('content.editor.stylesheets.%s', Str::snake($theme));

        $results = Event::dispatch($event, array($theme));

        foreach ($results as $result) {
            if (is_array($result) && ! empty($result)) {
                $stylesheets = array_merge($stylesheets, $result);
            }
        }

        return $stylesheets;
    }
}
