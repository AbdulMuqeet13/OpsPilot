export function FieldError({ message, id }: { message?: string; id?: string }) {
    if (!message) {
        return null;
    }

    return (
        <p id={id} className="mt-1.5 text-sm text-rose-600">
            {message}
        </p>
    );
}
