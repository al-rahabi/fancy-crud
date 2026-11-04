<?php

namespace FancyCrud\Excel\Enums;

use FancyCrud\Contracts\BaseEnumTrait;

/**
 * How an ExcelHeader gets its value out of a model.
 */
enum ExcelColumnSource: string
{
    use BaseEnumTrait;

    /** a column on the model's own table: `name` */
    case COLUMN = 'column';

    /** a dot path through loaded relations: `subject.name` */
    case RELATION = 'relation';

    /** an aliased value added to the query by a closure: `withCount`, `selectSub`, … */
    case QUERY = 'query';

    /** computed in PHP from the model by a closure */
    case CALCULATED = 'calculated';
}
