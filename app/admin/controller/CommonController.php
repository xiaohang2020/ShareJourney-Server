<?php
/**
 * FileName: 公共控制器
 * Description: 用于存放一些子类公共方法
 * @Author: XieGuanHua
 * @Email: wetalk.vip@foxmail.com
 * DateTime: 2021-11-27 20:43
 * Aphorism: 尊重是一种修养，知性而优雅，将人格魅力升华，大爱无声挥洒；尊重两个字看似轻于鸿毛，实则重于泰山。
 */

namespace app\admin\controller;


use app\admin\middleware\AuthMiddleware;
use app\admin\model\AdminLogModel;
use app\BaseController;
use think\facade\Config;
use think\facade\Db;
use think\facade\Filesystem;
use think\facade\Request;

class CommonController extends BaseController
{
    /**
     * 检测登录和权限中间件调用
     * @var string[]
     */
    protected $middleware = [AuthMiddleware::class];

    /**
     * 记录管理员日志
     * @param string $content 日志内容
     * @param int $type 日志类型（1为登录日志，2为操作日志）
     * @param int $id 管理员ID
     * @throws \think\db\exception\DbException
     */
    public static function log(string $content, int $type = 2, int $id = 0)
    {
        //删除大于60天的日志
        Db::name('admin_log')->where('create_time', '<= time', time() - (84600 * 60))->delete();
        //实例化对象
        $log = new AdminLogModel();
        //执行添加并过滤非数据表字段
        $log->save(['type' => $type, 'admin_id' => $id ?? request()->uid, 'content' => $content, 'ip' => Request::ip(), 'url' => Request::controller() . '/' . Request::action(), 'method' => Request::method()]);
    }

    /**
     * 上传文件
     * 支持文件name:image或者name:file
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function upload()
    {
        // 获取表单上传文件
        $files = request()->file();
        // 查询文件存储类型
        $system = Db::name('system')->where('id', 1)->field('file_storage,images_storage')->find();
        // 上传到本地服务器
        try {
            validate(['image|图片' => 'filesize:1567800|fileExt:jpg,jpeg,png,gif,ico,bmp', 'file|文件' => 'fileExt:zip,rar,7z,tar,gz'])->check($files);
            if (!is_array($files)) {
                // 判断上传的是图片还是文件
                if (isset($files['file'])) {
                    $type = $system['file_storage'];
                } else if (isset($files['image'])) {
                    $type = $system['images_storage'];
                } else {
                    // 如果上传的键不符合规范则只能上传到本地
                    $type = "0";
                }
            } else {
                // 如果上传为多文件，那只能存储在本地
                $type = "0";
            }
            switch ($type) {
                case "0":
                    $disk = "public"; //存储在本地
                    $url = request()->domain() . "/storage";
                    break;
                case "1":
                    $disk = "aliyun"; //存储在阿里云
                    $url = Config::get('filesystem.disks.aliyun.url');
                    break;
                case "2":
                    $disk = "qcloud"; //存储在腾讯云
                    $url = Config::get('filesystem.disks.qcloud.cdn');
                    break;
                case "3":
                    $disk = "qiniu"; //存储在七牛云
                    $url = Config::get('filesystem.disks.qiniu.url');
                    break;
                default:
                    show(403, "请求错误！");
            }
            $saveName = [];
            foreach ($files as $file) {
                if (is_array($file)) {
                    foreach ($file as $f) {
                        // 获取文件扩展名作存储路径
                        $fileType = $f->extension();
                        $saveName[] = $url . "/" . Filesystem::disk($disk)->putFile($fileType, $f);
                    }
                } else {
                    // 获取文件扩展名作存储路径
                    $fileType = $file->extension();
                    $saveName[] = $url . "/" . Filesystem::disk($disk)->putFile($fileType, $file);
                }
            }
            show(200, "上传成功！", $saveName);
        } catch (\think\exception\ValidateException $e) {
            show(500, $e->getMessage());
        }
    }
}