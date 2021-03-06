<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GroupRequest;
use App\Models\Group;
use App\Models\Image;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GroupController extends Controller
{
    public function __construct()
    {
        \Illuminate\Support\Facades\View::share([
            'default' => Group::getDefaultConfigs(),
            'positions' => Group::POSITIONS,
            'scenes' => Group::SCENES,
        ]);
    }

    public function index(Request $request): View
    {
        $groups = Group::query()->when($request->query('keywords'), function (Builder $builder, $keywords) {
            $builder->where('name', 'like', "%{$keywords}%");
        })->withCount('users')->withCount('strategies')->latest()->paginate();
        return view('admin.group.index', compact('groups'));
    }

    public function add(): View
    {
        return view('admin.group.add');
    }

    public function edit(Request $request): View
    {
        $group = Group::query()->findOrFail($request->route('id'));
        return view('admin.group.edit', compact('group'));
    }

    public function create(GroupRequest $request): Response
    {
        DB::transaction(function () use ($request) {
            $group = new Group();
            $group->fill($request->validated());
            $group->save();
        });

        return $this->success('εε»Ίζε');
    }

    public function update(GroupRequest $request): Response
    {
        DB::beginTransaction();
        try {
            /** @var Group $group */
            $group = Group::query()->findOrFail($request->route('id'));
            $group->fill($request->validated());
            if ($group->isDirty('is_default') && ! $group->is_default) {
                if (! Group::query()->where('is_default', true)->where('id', '<>', $group->id)->exists()) {
                    return $this->fail('η³»η»θ³ε°ιθ¦δΏηδΈδΈͺι»θ?€η»');
                }
            }
            if ($group->isDirty('is_guest') && ! $group->is_guest) {
                if (! Group::query()->where('is_guest', true)->where('id', '<>', $group->id)->exists()) {
                    return $this->fail('η³»η»θ³ε°ιθ¦δΏηδΈδΈͺζΈΈε?’η»');
                }
            }
            $group->save();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->fail('δΏε­ε€±θ΄₯');
        }

        return $this->success('δΏε­ζε');
    }

    public function delete(Request $request): Response
    {
        /** @var Group $group */
        if ($group = Group::query()->find($request->route('id'))) {
            if ($group->is_default || $group->is_guest) {
                return $this->fail('ι»θ?€η»εζΈΈε?’η»ζ ζ³ε ι€');
            }
            DB::transaction(function () use ($group) {
                $group->users()->update(['group_id' => Group::query()->where('is_default', true)->value('id')]);
                $group->delete();
            });
        }
        return $this->success('ε ι€ζε');
    }

    public function clearCache(Request $request): Response
    {
        /** @var Group $group */
        if ($group = Group::query()->find($request->route('id'))) {
            /** @var Image $image */
            foreach ($group->images()->select('key')->cursor() as $image) {
                Cache::forget('image_'.$image->key);
            }
        }
        return $this->success('ζΈι€ζε');
    }
}
