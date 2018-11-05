<?php
/**
 * Url Rewrite Import for Magento 1
 * @author Stanislav Chertilin <staschertilin@gmail.com>
 * @copyright 2018 https://github.com/stanislavfm/url-rewrite-import-m1
 */

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources;
use App\Airport;
use App\Flight;
use App\Transporter;

class FlightController extends Controller
{
    public function getFlights(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'number' => ['sometimes', 'required', 'string', 'max:' . Flight::NUMBER_LENGTH, 'regex:/^\d+$/'],
            'transporter' => ['sometimes', 'required', 'string', 'size:' . Transporter::CODE_LENGTH, 'exists:transporters,code'],
            'departureAirport'  => ['sometimes', 'required', 'string', 'size:' . Airport::CODE_LENGTH, 'exists:airports,code'],
            'arrivalAirport'    => ['sometimes', 'required', 'string', 'size:' . Airport::CODE_LENGTH, 'exists:airports,code'],
            'departureTime'     => ['sometimes', 'required', 'date'],
            'arrivalTime'       => ['sometimes', 'required', 'date'],
            'departureTimeFrom' => ['sometimes', 'required_with:departureTimeTo', 'date'],
            'departureTimeTo'   => ['sometimes', 'required_with:departureTimeFrom', 'date'],
            'arrivalTimeFrom'   => ['sometimes', 'required_with:arrivalTimeTo', 'date'],
            'arrivalTimeTo'     => ['sometimes', 'required_with:arrivalTimeFrom', 'date'],
        ]);

        if ($validator->fails()) {
            return [
                'status' => false,
                'message' => 'Validation failed.',
                'details' => $validator->errors()
            ];
        }

        if (
            $request->has('departureTime')
            && $request->hasAny(['departureTimeFrom', 'departureTimeTo'])
        ) {
            return [
                'status' => false,
                'message' => 'Invalid request parameters. Departure time should be specified without departure time from or departure time to fields.'
            ];
        }

        if (
            $request->has('arrivalTime')
            && $request->hasAny(['arrivalTimeFrom', 'arrivalTimeTo'])
        ) {
            return [
                'status' => false,
                'message' => 'Invalid request parameters. Arrival time should be specified without arrival time from or arrival time to fields.'
            ];
        }

        if (
            $request->hasAny(['departureTimeFrom', 'departureTimeTo'])
            && !$request->has(['departureTimeFrom', 'departureTimeTo'])
        ) {
            return [
                'status' => false,
                'message' => 'Invalid request parameters. It should be specified both departure time from and departure time to fields.'
            ];
        }

        if (
            $request->hasAny(['arrivalTimeFrom', 'arrivalTimeTo'])
            && !$request->has(['arrivalTimeFrom', 'arrivalTimeTo'])
        ) {
            return [
                'status' => false,
                'message' => 'Invalid request parameters. It should be specified both arrival time from and arrival time to fields.'
            ];
        }

        $flightsQuery = Flight::query();

        if ($request->has('number')) {
            $flightsQuery->where('number', $request->input('number'));
        }

        if ($request->has('transporter')) {
            $flightsQuery->whereHas('transporter', function ($query) use ($request) {
                $query->where('code', $request->input('transporter'));
            });
        }

        if ($request->has('departureAirport')) {
            $flightsQuery->whereHas('departureAirport', function ($query) use ($request) {
                $query->where('code', $request->input('departureAirport'));
            });
        }

        if ($request->has('arrivalAirport')) {
            $flightsQuery->whereHas('arrivalAirport', function ($query) use ($request) {
                $query->where('code', $request->input('arrivalAirport'));
            });
        }

        if ($request->has('departureTime')) {
            $flightsQuery->where('departureTime', $request->input('departureTime'));
        }

        if ($request->has('arrivalTime')) {
            $flightsQuery->where('arrivalTime', $request->input('arrivalTime'));
        }

        if ($request->has(['departureTimeFrom', 'departureTimeTo'])) {
            $flightsQuery->whereBetween('departureTime', [$request->input('departureTimeFrom'), $request->input('departureTimeTo')]);
        }

        if ($request->has(['arrivalTimeFrom', 'departureTimeTo'])) {
            $flightsQuery->whereBetween('arrivalTime', [$request->input('arrivalTimeFrom'), $request->input('arrivalTimeTo')]);
        }

        $flights = $flightsQuery->get();

        if ($flights->isEmpty()) {
            return [
                'status' => false,
                'message' => 'No flights found.'
            ];
        }

        return Resources\Flight::collection($flights);
    }

    public function createFlight(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'number' => ['required', 'string', 'max:' . Flight::NUMBER_LENGTH, 'regex:/^\d+$/', 'unique:flights,number'],
            'transporter' => ['required', 'string', 'size:' . Transporter::CODE_LENGTH, 'exists:transporters,code'],
            'departureAirport'  => ['required', 'string', 'size:' . Airport::CODE_LENGTH, 'different:arrivalAirport', 'exists:airports,code'],
            'arrivalAirport'    => ['required', 'string', 'size:' . Airport::CODE_LENGTH, 'different:departureAirport', 'exists:airports,code'],
            'departureTime'     => ['required', 'date_format:Y-m-d H:i:s', 'before:arrivalTime'],
            'arrivalTime'       => ['required', 'date_format:Y-m-d H:i:s', 'after:departureTime']
        ]);

        if ($validator->fails()) {
            return [
                'status' => false,
                'message' => 'Validation failed.',
                'details' => $validator->errors()
            ];
        }

        $flight = new Flight($request->only([
            'number', 'departureTime', 'arrivalTime'
        ]));

        $transporter = Transporter::where('code', $request->input('transporter'))->first();
        $departureAirport = Airport::where('code', $request->input('departureAirport'))->first();
        $arrivalAirport = Airport::where('code', $request->input('arrivalAirport'))->first();

        $flight->transporter()->associate($transporter);
        $flight->departureAirport()->associate($departureAirport);
        $flight->arrivalAirport()->associate($arrivalAirport);

        $flight->save();

        return new Resources\Flight($flight);
    }

    public function updateFlight(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'number' => ['required', 'string', 'max:' . Flight::NUMBER_LENGTH, 'regex:/^\d+$/', 'exists:flights,number'],
            'transporter' => ['sometimes', 'required_without_all:departureAirport,arrivalAirport,departureTime,arrivalTime', 'string', 'size:' . Transporter::CODE_LENGTH, 'exists:transporters,code'],
            'departureAirport'  => ['sometimes', 'required_without_all:transporter,arrivalAirport,departureTime,arrivalTime', 'string', 'size:' . Airport::CODE_LENGTH, 'exists:airports,code'],
            'arrivalAirport'    => ['sometimes', 'required_without_all:transporter,departureAirport,departureTime,arrivalTime', 'string', 'size:' . Airport::CODE_LENGTH, 'exists:airports,code'],
            'departureTime'     => ['sometimes', 'required_without_all:transporter,departureAirport,arrivalAirport,arrivalTime', 'date_format:Y-m-d H:i:s'],
            'arrivalTime'       => ['sometimes', 'required_without_all:transporter,departureAirport,arrivalAirport,departureTime', 'date_format:Y-m-d H:i:s']
        ]);

        if ($validator->fails()) {
            return [
                'status' => false,
                'message' => 'Validation failed.',
                'details' => $validator->errors()
            ];
        }

        if ($request->has(['departureTime', 'arrivalTime'])) {

            $departureTime = new \DateTime($request->input('departureTime'));
            $arrivalTime = new \DateTime($request->input('arrivalTime'));

            if ($departureTime >= $arrivalTime) {
                return [
                    'status' => false,
                    'message' => 'Departure time must less than arrival time.',
                ];
            }
        }

        if ($request->has(['departureAirport', 'arrivalAirport'])) {

            if ($request->input('departureAirport') === $request->input('arrivalAirport')) {
                return [
                    'status' => false,
                    'message' => 'Departure airport and arrival airport must be different.',
                ];
            }
        }

        $flight = Flight::where('number', $request->input('number'))->first();

        if ($request->has('departureTime') && !$request->has('arrivalTime')) {

            $departureTime = new \DateTime($request->input('departureTime'));

            if ($departureTime >= $flight->arrivalTime) {
                return [
                    'status' => false,
                    'message' => 'Departure time must less than current arrival time.',
                ];
            }
        }

        if ($request->has('arrivalTime') && !$request->has('departureTime')) {

            $arrivalTime = new \DateTime($request->input('arrivalTime'));

            if ($arrivalTime <= $flight->departureTime) {
                return [
                    'status' => false,
                    'message' => 'Arrival time must greater than current departure time.',
                ];
            }
        }

        if ($request->has('departureAirport')) {
            if ($request->input('departureAirport') === $flight->arrivalAirport->code) {
                return [
                    'status' => false,
                    'message' => 'Given departure airport is current arrival airport.',
                ];
            } else {
                $departureAirport = Airport::where('code', $request->input('departureAirport'))->first();
                $flight->departureAirport()->associate($departureAirport);
            }
        }

        if ($request->has('arrivalAirport')) {
            if ($request->input('arrivalAirport') === $flight->departureAirport->code) {
                return [
                    'status' => false,
                    'message' => 'Given arrival airport is current departure airport.',
                ];
            } else {
                $arrivalAirport = Airport::where('code', $request->input('departureAirport'))->first();
                $flight->arrivalAirport()->associate($arrivalAirport);
            }
        }

        if ($request->has('transporter')) {
            $transporter = Transporter::where('code', $request->input('transporter'))->first();
            $flight->transporter()->associate($transporter);
        }

        $flight->fill($request->only([
            'departureTime', 'arrivalTime'
        ]));

        $flight->save();

        return new Resources\Flight($flight);
    }

    public function deleteFlight(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'number' => ['required', 'string', 'max:' . Flight::NUMBER_LENGTH, 'regex:/^\d+$/', 'exists:flights,number'],
        ]);

        if ($validator->fails()) {
            return [
                'status' => false,
                'message' => 'Validation failed.',
                'details' => $validator->errors()
            ];
        }

        try {
            Flight::where('number', $request->input('number'))->delete();
        } catch (\Exception $exception) {
            return [
                'status' => false,
                'message' => $exception->getMessage()
            ];
        }

        return [
            'status' => true
        ];
    }
}