import { App } from "./resources/js/form-requests";

function useFormMock<T extends Record<string, any>>(fields: T) {
  return fields;
}

const form = useFormMock<App.Http.Requests.MemberRequest>({
  name: "John Doe",
});
