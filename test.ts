function useFormMock<T extends Record<string, unknown>>(fields: T) {
  return fields;
}

const form = useFormMock<App.Http.Requests.TestRequest>({
  name: "John Doe",
});
