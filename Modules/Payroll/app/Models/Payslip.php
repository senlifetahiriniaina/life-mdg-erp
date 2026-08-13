<?php
declare(strict_types=1);
namespace Modules\Payroll\Models;
use Illuminate\Database\Eloquent\Model;
use App\Traits\AuditableActions;

class Payslip extends Model
{
    use AuditableActions;
    protected $table = 'payslips';
    protected $fillable = ['payroll_run_id','tenant_id','employee_id','employee_name','period','salary_components','gross_salary','total_deductions','net_salary','currency','status','paid_at'];
    protected $casts = ['period'=>'date','salary_components'=>'array','gross_salary'=>'decimal:2','total_deductions'=>'decimal:2','net_salary'=>'decimal:2','paid_at'=>'datetime'];
}
