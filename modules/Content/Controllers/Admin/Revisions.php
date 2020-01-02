<?php

namespace Modules\Content\Controllers\Admin;

use Nova\Database\ORM\ModelNotFoundException;
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

        return $this->createView(compact('type', 'name', 'post', 'revisions'))
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

        if (preg_match('#^(?:\d+)-revision-v(\d+)$#', $revision->name, $matches) !== 1) {
            $version = 0;
        } else {
            $version = (int) $matches[1];
        }

        $revision->delete();

        //
        $postType = PostType::make($post->type);

        $status = __d('content', 'The Revision <b>{0}</b> of {1} <b>#{2}</b> was successfully deleted.', $version, $postType->label('name'), $post->id);

        return Redirect::back()->with('success', $status);
    }
}
