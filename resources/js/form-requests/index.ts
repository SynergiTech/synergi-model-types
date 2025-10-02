export namespace App.Http.Requests {
  /**
   * @see App\Http\Requests\TestRequest2
   */
  export interface TestRequest2 {
    just_a_string: boolean;
  }

  /**
   * @see App\Http\Requests\TestRequest
   */
  export interface TestRequest {
    just_a_string: boolean;
    array_of_rules_as_seperator?: any[];
    array_of_rules_as_array: string;
    sometimes_required?: string;
    required_rule: string;
    nullable_rule?: string;
    rule_array: any[];
  }
}

export {};
