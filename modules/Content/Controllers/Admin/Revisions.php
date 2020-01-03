<?php

namespace Modules\Content\Controllers\Admin;

use Nova\Database\ORM\ModelNotFoundException;
use Nova\Support\Facades\Cache;
use Nova\Support\Facades\Redirect;

use Modules\Content\Models\Post;
use Modules\Content\Support\Facades\PostType;
use Modules\Platform\Controllers\Admin\BaseController;


class Revisions extends BaseController
{

    public function index($id)
    {
        try {
            $post = Post::findOrFail($id);
        }
        catch (ModelNotFoundException $e) {
            return Redirect::back()->with('danger', __d('content', 'Record not found: #{0}', $id));
        }

        $postType = PostType::make($post->type);

        $revisions = $post->revisions()
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        //
        $name = $postType->label('name');

        return $this->createView(compact('name', 'post', 'revisions', 'postType'))
            ->shares('title', __d('content', 'Revisions of the {0} : {1}', $name, $post->title));
    }

    protected function destroy($id)
    {
        try {
            $revision = Post::where('type', 'revision')->findOrFail($id);
        }
        catch (ModelNotFoundException $e) {
            return Redirect::back()->with('danger', __d('content', 'Record not found: #{0}', $id));
        }

        $post = $revision->parent()->first();

        if (preg_match('#^(?:\d+)-revision-v(\d+)$#', $revision->name, $matches) === 1) {
            $version = (int) $matches[1];
        } else {
            $version = 0;
        }

        $revision->delete();

        //
        $postType = PostType::make($post->type);

        $status = __d('content', 'The Revision <b>{0}</b> of {1} <b>#{2}</b> was successfully deleted.', $version, $postType->label('name'), $post->id);

        return Redirect::back()->with('success', $status);
    }

    public function restore($id)
    {
        try {
            $revision = Post::where('type', 'revision')->findOrFail($id);
        }
        catch (ModelNotFoundException $e) {
            return Redirect::back()->with('danger', __d('content', 'Record not found: #{0}', $id));
        }

        $post = $revision->parent()->first();

        // Restore the parent Post from Revision variables.
        $post->content    = $revision->content;
        $post->excerpt    = $revision->excerpt;
        $post->title      = $revision->title;
        $post->password   = $revision->password;
        $post->menu_order = $revision->menu_order;
        $post->mime_type  = $revision->mime_type;

        $post->save();

        // Handle the MetaData.
        if (preg_match('#^(?:\d+)-revision-v(\d+)$#', $revision->name, $matches) === 1) {
            $version = (int) $matches[1];
        } else {
            $version = 0;
        }

        $post->saveMeta('version', $version);

        // Invalidate the content caches.
        Cache::section('content')->flush();

        //
        $postType = PostType::make($post->type);

        $status = __d('content', 'The {0} <b>#{1}</b> was successfully restored to the revision: <b>{2}</b>', $postType->label('name'), $post->id, $version);

        return Redirect::back()->with('success', $status);
    }
}
