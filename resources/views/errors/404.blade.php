<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            404 ページが見つかりません
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 text-center">
                    <p class="py-8 text-gray-600">お探しのページは存在しないか、削除された可能性があります。</p>
                    <a href="{{ url('/books') }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        書籍一覧へ戻る
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
