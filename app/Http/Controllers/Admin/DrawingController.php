<?php

namespace App\Http\Controllers\Admin;

use App\Models\Member;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Drawing;
use DB;
class DrawingController extends Controller
{
    public function index(Request $request)
    {
        $mod = new Drawing();

        $status = $name = $start_at = $end_at ='';
        if ($request->has('name'))
        {
            $name = $request->get('name');
            $m_list = Member::where('name', 'LIKE', "%$name%")->pluck('id');
            $mod = $mod->whereIn('member_id', $m_list);
        }
        if ($request->has('status'))
        {
            $status = $request->get('status');
            $mod = $mod->where('status', $status);
        }
        if ($request->has('start_at'))
        {
            $start_at = $request->get('start_at');
            $mod = $mod->where('created_at', '>=', $start_at);
        }
        if ($request->has('end_at'))
        {
            $end_at = $request->get('end_at');
            $mod = $mod->where('created_at', '<=',$end_at);
        }

        $data = $mod->orderBy('created_at', 'desc')->paginate(config('admin.page-size'));

        $total_money = $mod->sum('money');
        $total_counter_fee = $mod->sum('counter_fee');

        return view('admin.drawing.index', compact('data', 'name','status', 'total_money', 'total_counter_fee','start_at', 'end_at'));
    }

    public function show($id)
    {
        $data = Drawing::findOrFail($id);

        return view('admin.drawing.show', compact('data'));
    }

    //提款成功
    public function confirm(Request $request, $id)
    {

        if ($request->get('money') < 1)
            return responseWrong('提款金额不达标');

        $mod = Drawing::findOrFail($id);
        $data = $request->all();
        try{
            DB::transaction(function() use($mod, $data,$request) {

                $mod->update([
                    'status' => 2,
                    'confirm_at' => date('Y-m-d H:i:s'),
                    'counter_fee' => $request->get('counter_fee') ? : 0,
                    'user_id' => \Auth::user()->id
                ]);

                //用户中心账户减钱
                //$mod->member()->decrement('money', $mod->money);



            });
        }catch(Exception $e){
            DB::rollback();
            return respF('创建失败');
        }

        return responseSuccess('', '确认提款成功', route('drawing.index'));
    }

    public function create()
    {
        return view('admin.drawing.create');
    }

    public function store(Request $request)
    {
        $validator = $this->verify($request, 'drawing.store');

        if ($validator->fails())
        {
            $messages = $validator->messages()->toArray();
            return responseWrong($messages);
        }

        $data = $request->all();

        Drawing::create($data);

        return responseSuccess('', '', route('drawing.index'));

    }

    public function edit($id)
    {
        $data = Drawing::findOrFail($id);

        return view('admin.drawing.edit', compact('data'));
    }

    public function update(Request $request, $id)
    {

        $mod = Drawing::findOrFail($id);

        try{
            DB::transaction(function() use($mod, $request) {

                $mod->update([
                    'fail_reason' => $request->get('fail_reason'),
                    'status' => 3,
                    'user_id' => \Auth::user()->id
                ]);

                //恢复用户中心账户里的金额
                $mod->member()->increment('money', $mod->money);



            });
        }catch(Exception $e){
            DB::rollback();
            return respF('创建失败');
        }


        return respS();

    }

    public function destroy($id)
    {
        Drawing::destroy($id);

        return respS();
    }
}
