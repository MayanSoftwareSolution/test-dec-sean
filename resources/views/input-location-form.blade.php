<!DOCTYPE html>
<html>
<head>
    <title>Kinetic PHP Test </title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-10">
<div class="max-w-md mx-auto bg-white p-8 rounded shadow">
    <h1 class="text-xl font-bold text-center mb-6">Kinetic PHP Test - Sean Bezuidenhout</h1>
    <h1 class="text-sm font-semibold text-center mb-6">5th December 2024</h1>
    <form id="locationForm" class="space-y-4">
        <div>
            <label class="block mb-2">Latitude</label>
            <input type="text" name="latitude" required
                   class="w-full px-3 py-2 border rounded">
        </div>
        <div>
            <label class="block mb-2">Longitude</label>
            <input type="text" name="longitude" required
                   class="w-full px-3 py-2 border rounded">
        </div>
        <div>
            <label class="block mb-2">Accuracy (metres)</label>
            <input type="number" name="accuracy" required
                   class="w-full px-3 py-2 border rounded">
        </div>
        <button type="submit" class="w-full bg-blue-500 text-white py-2 rounded hover:bg-blue-600">
            Check Location
        </button>
    </form>
    <div id="result" class="mt-4 p-3 rounded text-center"></div>

    <div class="mt-6">
        <h2 class="font-bold mb-2">Test Cases</h2>
        <table class="w-full border">
            <thead>
            <tr class="bg-gray-200">
                <th class="border p-2">Latitude</th>
                <th class="border p-2">Longitude</th>
                <th class="border p-2">Accuracy</th>
            </tr>
            </thead>
            <tbody>
            @foreach($testCases as $case)
                <tr>
                    <td class="border p-2">{{ $case['lat'] }}</td>
                    <td class="border p-2">{{ $case['lon'] }}</td>
                    <td class="border p-2">{{ $case['accuracy'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

<script>
    document.getElementById('locationForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(e.target);

        fetch('{{ route("location.check") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                latitude: formData.get('latitude'),
                longitude: formData.get('longitude'),
                accuracy: formData.get('accuracy')
            })
        })
            .then(response => response.json())
            .then(data => {
                const resultDiv = document.getElementById('result');
                resultDiv.textContent = data.message;
                resultDiv.className = `mt-4 p-3 rounded text-center ${data.result ? 'bg-green-200' : 'bg-red-200'}`;
            })
            .catch(error => {
                console.error('Error:', error);
            });
    });
</script>
</body>
</html>
