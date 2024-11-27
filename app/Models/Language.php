<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Language extends Model
{
    use HasFactory, SoftDeletes;

    // Tên bảng trong cơ sở dữ liệu
    protected $table = 'languages';

    // Các cột có thể được gán giá trị một cách trực tiếp
    protected $fillable = [
        'name',
        'description',
        'status',
        'deleted_by',
        'created_by',
        'updated_by',
    ];


    /**
     * Truy vấn để lấy các bản ghi có trạng thái 'active'.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */

    /**
     * Định nghĩa mối quan hệ với người dùng đã xóa.
     */
    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * Định nghĩa mối quan hệ với người dùng tạo bản ghi.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Định nghĩa mối quan hệ với người dùng cập nhật bản ghi.
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
    public function courses()
    {
        return $this->hasMany(Course::class, 'language_id');
    }
}
