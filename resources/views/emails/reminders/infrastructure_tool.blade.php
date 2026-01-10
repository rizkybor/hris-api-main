<x-mail::message>
{{-- Header --}}
# ⚠️ Reminder: Infrastructure Tool Expiring on {{ $targetDate }}

{{-- Greeting --}}
Dear Team,

This is an official notification from **PT. Jendela Cakra Digital** regarding Infrastructure Tools that are
scheduled to expire on **{{ $targetDate }}**.  
Please review the list below and take the necessary action promptly to avoid service disruptions.

{{-- Table --}}
<x-mail::table>
| Tech Stack Component | Vendor |
|---------------------|--------|
@foreach ($tools as $tool)
| {{ $tool['tech_stack_component'] }} | {{ $tool['vendor'] }} |
@endforeach
</x-mail::table>

{{-- Call-to-Action --}}
<x-mail::button :url="$actionUrl" color="success">
Review Tools
</x-mail::button>

{{-- Additional Info --}}
If you have any questions or need assistance, please contact our IT department:

- 📧 Email: contact@jcdigital.co.id  
- 📞 Phone: +62 87 8279 2511  

Thank you for your prompt attention.

Best regards,<br>
**PT. Jendela Cakra Digital**  
Jl. Pd. Cabe Raya No.7, Pd. Cabe Udik, Kec. Pamulang, Kota Tangerang Selatan, Banten 15418  
📧 info@jcdigital.co.id | 🌐 www.jcdigital.co.id  
📞 +62 87 8279 2511
</x-mail::message>
