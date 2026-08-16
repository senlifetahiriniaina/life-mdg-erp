<?php
declare(strict_types=1);
namespace Modules\Payroll\Models;
use Illuminate\Database\Eloquent\Model;
use App\Traits\AuditableActions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    use AuditableActions, HasFactory;
    protected $table = 'payroll_runs';
    protected $fillable = ['tenant_id','period','status','currency','total_gross','total_deductions','total_net','processed_by','processed_at','validated_at'];
    protected $casts = ['period'=>'date','total_gross'=>'decimal:2','total_deductions'=>'decimal:2','total_net'=>'decimal:2','processed_at'=>'datetime','validated_at'=>'datetime'];
    public function payslips(): HasMany { return $this->hasMany(Payslip::class,'payroll_run_id'); }
}
