<?php
declare(strict_types=1);
namespace Modules\Payroll\Models;
use Illuminate\Database\Eloquent\Model;
use App\Traits\AuditableActions;

class SalaryComponent extends Model
{
    use AuditableActions;
    protected $table = 'salary_components';
    protected $fillable = ['tenant_id','name','component_type','calculation_type','amount','rate','is_taxable','is_statutory','applies_to','is_active'];
    protected $casts = ['amount'=>'decimal:2','rate'=>'decimal:4','is_taxable'=>'boolean','is_statutory'=>'boolean','is_active'=>'boolean','applies_to'=>'array'];
}
